<?php
/**
 *  ______  __         ______               __    __
 * |   __ \|__|.-----.|   __ \.----..-----.|  |_ |  |--..-----..----.
 * |   __ <|  ||  _  ||   __ <|   _||  _  ||   _||     ||  -__||   _|
 * |______/|__||___  ||______/|__|  |_____||____||__|__||_____||__|
 *             |_____|
 *
 * BigBrother plugin for PocketMine-MP
 * Copyright (C) 2014-2015 shoghicp <https://github.com/shoghicp/BigBrother>
 * Copyright (C) 2016- BigBrotherTeam
 * Copyright (C) 2026 - Updated for PocketMine-MP 5.x by XGDAVID <https://github.com/xgdavid>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author BigBrotherTeam
 * @link   https://github.com/BigBrotherTeam/BigBrother
 *
 */

declare(strict_types=1);

namespace shoghicp\BigBrother\network;

use pocketmine\thread\log\ThreadSafeLogger;
use shoghicp\BigBrother\utils\Binary;

class ServerManager
{

	public const VERSION = "1.12.2";
	public const PROTOCOL = 340;

	/*
	 * Internal Packet:
	 * int32 (length without this field)
	 * byte (packet ID)
	 * payload
	 */

	/*
	 * SEND_PACKET payload:
	 * int32 (session identifier)
	 * packet (binary payload)
	 */
	public const PACKET_SEND_PACKET = 0x01;

	/*
	 * OPEN_SESSION payload:
	 * int32 (session identifier)
	 * byte (address length)
	 * byte[] (address)
	 * short (port)
	 */
	public const PACKET_OPEN_SESSION = 0x02;

	/*
	 * CLOSE_SESSION payload:
	 * int32 (session identifier)
	 */
	public const PACKET_CLOSE_SESSION = 0x03;

	/*
	 * ENABLE_ENCRYPTION payload:
	 * int32 (session identifier)
	 * byte[] (secret)
	 */
	public const PACKET_ENABLE_ENCRYPTION = 0x04;

	/*
	 * SET_COMPRESSION payload:
	 * int32 (session identifier)
	 * int (threshold)
	 */
	public const PACKET_SET_COMPRESSION = 0x05;

	public const PACKET_SET_OPTION = 0x06;

	/*
	 * RECEIVE_PACKET payload:
	 * int32 (session identifier)
	 * packet (binary payload)
	 */
	public const PACKET_RECEIVE_PACKET = 0x07;

	/*
	 * no payload
	 */
	public const PACKET_SHUTDOWN = 0xfe;

	/*
	 * no payload
	 */
	public const PACKET_EMERGENCY_SHUTDOWN = 0xff;
	/** @var string[] */
	public array $sample = [];
	/** @var string */
	public string $description;
	/** @var string|null */
	public ?string $favicon;
	/** @var array */
	public array $serverData = [
		"MaxPlayers" => 20,
		"OnlinePlayers" => 0,
	];
	/** @var ServerThread */
	protected ServerThread $thread;
	/** @var resource|null */
	protected $socket;
	/** @var int */
	protected int $identifier = 0;
	/** @var resource[] */
	protected array $sockets = [];
	/** @var Session[] */
	protected array $sessions = [];
	/** @var ThreadSafeLogger */
	protected ThreadSafeLogger $logger;
	/** @var bool */
	protected bool $shutdown = false;

	/**
	 * @param ServerThread $thread
	 * @param int $port
	 * @param string $interface
	 * @param string $description
	 * @param string|null $favicon
	 */
	public function __construct(ServerThread $thread, int $port, string $interface, string $description = "", ?string $favicon = null)
	{
		$this->thread = $thread;
		$this->description = $description;

		if ($favicon === null || !file_exists($favicon) || ($image = file_get_contents($favicon)) === false || $image === "") {
			$this->favicon = null;
		} else {
			$this->favicon = "data:image/png;base64," . base64_encode($image);
		}

		$this->logger = $this->thread->getLogger();

		if ($interface === "") {
			$interface = "0.0.0.0";
		}

		$this->socket = stream_socket_server("tcp://$interface:$port", $errno, $errstr, STREAM_SERVER_LISTEN | STREAM_SERVER_BIND);
		if (!$this->socket) {
			$this->logger->critical("[BigBrother] **** FAILED TO BIND TO " . $interface . ":" . $port . "!");
			$this->logger->critical("[BigBrother] Perhaps a server is already running on that port?");
			return;
		}

		$this->sockets[-1] = $this->socket;

		$this->process();
	}

	private function process(): void
	{
		while ($this->shutdown !== true) {
			// Process any pending packets from main thread first
			while ($this->processPacket()) {
				// Process all available packets
			}

			$sockets = $this->sockets;
			$write = null;
			$except = null;
			// Use a short timeout (50ms) to allow checking for packets from the main thread
			if (@stream_select($sockets, $write, $except, 0, 50000) > 0) {
				if (isset($sockets[-1])) {
					unset($sockets[-1]);
					$connection = stream_socket_accept($this->socket, 0);
					if ($connection) {
						$this->identifier++;
						$this->sockets[$this->identifier] = $connection;
						$this->sessions[$this->identifier] = new Session($this, $this->identifier, $connection);
					}
				}

				foreach ($sockets as $identifier => $socket) {
					if (isset($this->sessions[$identifier]) && $this->sockets[$identifier] === $socket) {
						$this->sessions[$identifier]->process();
					} else {
						$this->findSocket($socket);
					}
				}
			}
		}
	}

	/**
	 * @return bool false if there is no packet to process else true
	 */
	protected function processPacket(): bool
	{
		$packet = $this->thread->readMainToThreadPacket();
		if (is_string($packet)) {
			$pid = ord($packet[0]);
			$buffer = substr($packet, 1);

			switch ($pid) {
				case self::PACKET_SEND_PACKET:
					$id = Binary::readInt(substr($buffer, 0, 4));
					$data = substr($buffer, 4);

					if (!isset($this->sessions[$id])) {
						$this->closeSession($id);
						return true;
					}
					$this->sessions[$id]->writeRaw($data);
					break;

				case self::PACKET_ENABLE_ENCRYPTION:
					$id = Binary::readInt(substr($buffer, 0, 4));
					$secret = substr($buffer, 4);

					if (!isset($this->sessions[$id])) {
						$this->closeSession($id);
						return true;
					}
					$this->sessions[$id]->enableEncryption($secret);
					break;

				case self::PACKET_SET_COMPRESSION:
					$id = Binary::readInt(substr($buffer, 0, 4));
					$threshold = Binary::readInt(substr($buffer, 4, 4));

					if (!isset($this->sessions[$id])) {
						$this->closeSession($id);
						return true;
					}
					$this->sessions[$id]->setCompression($threshold);
					break;

				case self::PACKET_SET_OPTION:
					$offset = 1;
					$len = ord($packet[$offset++]);
					$name = substr($packet, $offset, $len);
					$offset += $len;
					$value = substr($packet, $offset);
					switch ($name) {
						case "name":
							$decoded = json_decode($value, true);
							if (is_array($decoded)) {
								$this->serverData = $decoded;
							}
							break;
					}
					break;

				case self::PACKET_CLOSE_SESSION:
					$id = Binary::readInt(substr($buffer, 0, 4));
					if (isset($this->sessions[$id])) {
						$this->close($this->sessions[$id]);
					} else {
						$this->closeSession($id);
					}
					break;

				case self::PACKET_SHUTDOWN:
					foreach ($this->sessions as $session) {
						$session->close();
					}

					$this->shutdown();
					if ($this->socket) {
						stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
					}
					$this->shutdown = true;
					break;

				case self::PACKET_EMERGENCY_SHUTDOWN:
					$this->shutdown = true;
					break;
			}

			return true;
		}

		return false;
	}

	/**
	 * @param int $id
	 */
	protected function closeSession(int $id): void
	{
		$this->thread->pushThreadToMainPacket(chr(self::PACKET_CLOSE_SESSION) . Binary::writeInt($id));
	}

	/**
	 * @param Session $session
	 */
	public function close(Session $session): void
	{
		$identifier = $session->getID();
		if (isset($this->sockets[$identifier])) {
			fclose($this->sockets[$identifier]);
			unset($this->sockets[$identifier]);
		}
		unset($this->sessions[$identifier]);
		$this->closeSession($identifier);
	}

	public function shutdown(): void
	{
		$this->thread->shutdown();
		usleep(50000); // Sleep for 1 tick
	}

	/**
	 * @param resource $s
	 */
	protected function findSocket($s): void
	{
		foreach ($this->sockets as $identifier => $socket) {
			if ($identifier > 0 && $socket === $s) {
				$this->sessions[$identifier]->process();
				break;
			}
		}
	}

	/**
	 * @return array
	 */
	public function getServerData(): array
	{
		return $this->serverData;
	}

	/**
	 * @param int $id
	 * @param string $buffer
	 */
	public function sendPacket(int $id, string $buffer): void
	{
		$this->thread->pushThreadToMainPacket(chr(self::PACKET_RECEIVE_PACKET) . Binary::writeInt($id) . $buffer);
	}

	/**
	 * @param Session $session
	 */
	public function openSession(Session $session): void
	{
		$data = chr(self::PACKET_OPEN_SESSION) . Binary::writeInt($session->getID()) . chr(strlen($session->getAddress())) . $session->getAddress() . Binary::writeShort($session->getPort());
		$this->thread->pushThreadToMainPacket($data);
	}
}

