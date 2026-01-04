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

use Exception;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;

class ServerThread extends Thread
{

	public const VERSION = "1.12.2";
	public const PROTOCOL = 340;

	/** @var int */
	protected int $port;

	/** @var string */
	protected string $interface;

	/** @var ThreadSafeLogger */
	protected ThreadSafeLogger $logger;

	/** @var string */
	protected string $data;

	/** @var bool */
	protected bool $shutdown = false;

	/** @var ThreadSafeArray */
	protected ThreadSafeArray $externalQueue;

	/** @var ThreadSafeArray */
	protected ThreadSafeArray $internalQueue;

	/**
	 * @param ThreadSafeLogger $logger
	 * @param int $port 1-65536
	 * @param string $interface
	 * @param string $motd
	 * @param string|null $icon
	 * @throws Exception
	 */
	public function __construct(
		ThreadSafeLogger $logger,
		int              $port,
		string           $interface = "0.0.0.0",
		string           $motd = "Minecraft Server",
		?string          $icon = null
	)
	{
		$this->port = $port;
		if ($port < 1 || $port > 65536) {
			throw new Exception("Invalid port range");
		}

		$this->interface = $interface;
		$this->logger = $logger;

		$this->data = serialize([
			"motd" => $motd,
			"icon" => $icon
		]);

		$this->externalQueue = new ThreadSafeArray();
		$this->internalQueue = new ThreadSafeArray();
	}

	/**
	 * @return bool true if this thread state is shutdown
	 */
	public function isShutdown(): bool
	{
		return $this->shutdown === true;
	}

	public function shutdown(): void
	{
		$this->shutdown = true;
	}

	/**
	 * @return int port
	 */
	public function getPort(): int
	{
		return $this->port;
	}

	/**
	 * @return string interface
	 */
	public function getInterface(): string
	{
		return $this->interface;
	}

	/**
	 * @return ThreadSafeLogger logger
	 */
	public function getLogger(): ThreadSafeLogger
	{
		return $this->logger;
	}

	/**
	 * @return ThreadSafeArray external queue
	 */
	public function getExternalQueue(): ThreadSafeArray
	{
		return $this->externalQueue;
	}

	/**
	 * @return ThreadSafeArray internal queue
	 */
	public function getInternalQueue(): ThreadSafeArray
	{
		return $this->internalQueue;
	}

	/**
	 * @param string $str
	 */
	public function pushMainToThreadPacket(string $str): void
	{
		$this->internalQueue[] = $str;
	}

	/**
	 * @return string|null
	 */
	public function readMainToThreadPacket(): ?string
	{
		return $this->internalQueue->shift();
	}

	/**
	 * @param string $str
	 */
	public function pushThreadToMainPacket(string $str): void
	{
		$this->externalQueue[] = $str;
	}

	/**
	 * @return string|null
	 */
	public function readThreadToMainPacket(): ?string
	{
		return $this->externalQueue->shift();
	}

	public function shutdownHandler(): void
	{
		if ($this->shutdown !== true) {
			$this->logger->emergency("[ServerThread] ServerThread crashed!");
		}
	}

	/**
	 * @override
	 */
	protected function onRun(): void
	{
		register_shutdown_function([$this, "shutdownHandler"]);

		$data = unserialize($this->data, ["allowed_classes" => false]);
		new ServerManager($this, $this->port, $this->interface, $data["motd"], $data["icon"]);
	}
}

