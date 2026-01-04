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

use pocketmine\network\NetworkInterface;
use pocketmine\Server;
use shoghicp\BigBrother\BigBrother;
use shoghicp\BigBrother\utils\Binary;
use Throwable;

class ProtocolInterface implements NetworkInterface
{

    /** @var BigBrother */
    protected BigBrother $plugin;

    /** @var Server */
    protected Server $server;

    /** @var Translator */
    protected Translator $translator;

    /** @var ServerThread */
    protected ServerThread $thread;

    /** @var array<int, JavaPlayer> */
    protected array $sessions = [];

    /** @var int */
    private int $threshold;

    /**
     * @param BigBrother $plugin
     * @param Server $server
     * @param Translator $translator
     * @param int $threshold
     */
    public function __construct(BigBrother $plugin, Server $server, Translator $translator, int $threshold)
    {
        $this->plugin = $plugin;
        $this->server = $server;
        $this->translator = $translator;
        $this->threshold = $threshold;

        $this->thread = new ServerThread(
            $server->getLogger(),
            $plugin->getPort(),
            $plugin->getIp(),
            $plugin->getMotd(),
            $plugin->getDataFolder() . "server-icon.png"
        );
    }

    /**
     * @override
     */
    public function start(): void
    {
        $this->thread->start();
        $this->plugin->getLogger()->info("Java Edition protocol interface started");
    }

    /**
     * @override
     */
    public function setName(string $name): void
    {
        $info = $this->server->getQueryInformation();
        $value = [
            "MaxPlayers" => $info->getMaxPlayerCount(),
            "OnlinePlayers" => $info->getPlayerCount(),
        ];
        $buffer = chr(ServerManager::PACKET_SET_OPTION) . chr(strlen("name")) . "name" . json_encode($value);
        $this->thread->pushMainToThreadPacket($buffer);
    }

    /**
     * @override
     */
    public function tick(): void
    {
        $this->process();
    }

    /**
     * Process incoming packets from the server thread
     */
    protected function process(): void
    {
        while (($packet = $this->thread->readThreadToMainPacket()) !== null && strlen($packet) > 0) {
            $id = ord($packet[0]);
            $offset = 1;

            switch ($id) {
                case ServerManager::PACKET_OPEN_SESSION:
                    $identifier = Binary::readInt(substr($packet, $offset, 4));
                    $offset += 4;
                    $addressLength = ord($packet[$offset++]);
                    $address = substr($packet, $offset, $addressLength);
                    $offset += $addressLength;
                    $port = Binary::readShort(substr($packet, $offset, 2));

                    $this->openSession($identifier, $address, $port);
                    break;

                case ServerManager::PACKET_CLOSE_SESSION:
                    $identifier = Binary::readInt(substr($packet, $offset, 4));
                    $this->closeSession($identifier);
                    break;

                case ServerManager::PACKET_RECEIVE_PACKET:
                    $identifier = Binary::readInt(substr($packet, $offset, 4));
                    $offset += 4;
                    $buffer = substr($packet, $offset);
                    $this->handlePacket($identifier, $buffer);
                    break;
            }
        }
    }

    /**
     * Open a new session
     *
     * @param int $identifier
     * @param string $address
     * @param int $port
     */
    protected function openSession(int $identifier, string $address, int $port): void
    {
        $this->sessions[$identifier] = new JavaPlayer($this, $identifier, $address, $port, $this->plugin);
        $this->plugin->getLogger()->debug("New Java Edition connection from $address:$port (session $identifier)");
    }

    /**
     * Close a session (called when receiving PACKET_CLOSE_SESSION from thread)
     *
     * @param int $identifier
     */
    public function closeSession(int $identifier): void
    {
        if (isset($this->sessions[$identifier])) {
            $player = $this->sessions[$identifier];
            unset($this->sessions[$identifier]);
            $this->plugin->getLogger()->info("Java Edition player '{$player->getUsername()}' disconnected");
        }
    }

    /**
     * Handle incoming packet
     *
     * @param int $identifier
     * @param string $buffer
     */
    protected function handlePacket(int $identifier, string $buffer): void
    {
        if (isset($this->sessions[$identifier])) {
            $this->sessions[$identifier]->handleRawPacket($buffer);
        }
    }

    /**
     * Close a session and notify the thread
     *
     * @param int $identifier
     */
    public function closeSessionFromMain(int $identifier): void
    {
        if (isset($this->sessions[$identifier])) {
            $player = $this->sessions[$identifier];
            unset($this->sessions[$identifier]);
            // Send close packet to thread
            $buffer = chr(ServerManager::PACKET_CLOSE_SESSION) . Binary::writeInt($identifier);
            $this->thread->pushMainToThreadPacket($buffer);
        }
    }

    /**
     * @override
     */
    public function shutdown(): void
    {
        $this->thread->pushMainToThreadPacket(chr(ServerManager::PACKET_SHUTDOWN));
        $this->thread->join();
    }

    /**
     * Send packet to client
     *
     * @param int $identifier
     * @param Packet $packet
     */
    public function sendPacket(int $identifier, Packet $packet): void
    {
        try {
            $data = chr(ServerManager::PACKET_SEND_PACKET) . Binary::writeInt($identifier) . $packet->write();
            $this->thread->pushMainToThreadPacket($data);
        } catch (Throwable $e) {
            $this->plugin->getLogger()->debug("Failed to send packet: " . $e->getMessage());
        }
    }

    /**
     * Send raw data to client
     *
     * @param int $identifier
     * @param string $data
     */
    public function sendRaw(int $identifier, string $data): void
    {
        $buffer = chr(ServerManager::PACKET_SEND_PACKET) . Binary::writeInt($identifier) . $data;
        $this->thread->pushMainToThreadPacket($buffer);
    }

    /**
     * Enable compression for a session
     *
     * @param int $identifier
     */
    public function setCompression(int $identifier): void
    {
        $buffer = chr(ServerManager::PACKET_SET_COMPRESSION) . Binary::writeInt($identifier) . Binary::writeInt($this->threshold);
        $this->thread->pushMainToThreadPacket($buffer);
    }

    /**
     * Enable encryption for a session
     *
     * @param int $identifier
     * @param string $secret
     */
    public function enableEncryption(int $identifier, string $secret): void
    {
        $buffer = chr(ServerManager::PACKET_ENABLE_ENCRYPTION) . Binary::writeInt($identifier) . $secret;
        $this->thread->pushMainToThreadPacket($buffer);
    }

    /**
     * @return BigBrother
     */
    public function getPlugin(): BigBrother
    {
        return $this->plugin;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * @return Translator
     */
    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    /**
     * @return int
     */
    public function getThreshold(): int
    {
        return $this->threshold;
    }

    /**
     * Broadcast a chat message to all Java players
     *
     * @param string $message
     */
    public function broadcastChatToJava(string $message): void
    {
        foreach ($this->sessions as $session) {
            $session->sendChatMessage($message);
        }
    }

    /**
     * Get all connected Java players
     *
     * @return JavaPlayer[]
     */
    public function getJavaPlayers(): array
    {
        return $this->sessions;
    }

    /**
     * Get Java player count
     *
     * @return int
     */
    public function getJavaPlayerCount(): int
    {
        return count($this->sessions);
    }
}

