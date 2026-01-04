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

use shoghicp\BigBrother\BigBrother;
use shoghicp\BigBrother\utils\Binary;

class JavaPlayer
{
	private ProtocolInterface $interface;
	private int $identifier;
	private string $address;
	private int $port;
	private BigBrother $plugin;
	private int $status = 0;
	private string $username = "";
	private string $uuid = "";
	private int $lastKeepAlive = 0;

	public function __construct(ProtocolInterface $interface, int $identifier, string $address, int $port, BigBrother $plugin)
	{
		$this->interface = $interface;
		$this->identifier = $identifier;
		$this->address = $address;
		$this->port = $port;
		$this->plugin = $plugin;
		$this->lastKeepAlive = time();
	}

	public function handleRawPacket(string $buffer): void
	{
		$offset = 0;
		$pid = Binary::readComputerVarInt($buffer, $offset);

		if ($this->status === 0) {
			$this->handleLoginPacket($pid, $buffer, $offset);
		} else {
			$this->handlePlayPacket($pid, $buffer, $offset);
		}
	}

	private function handleLoginPacket(int $pid, string $buffer, int $offset): void
	{
		if ($pid === 0x00) {
			$usernameLength = Binary::readComputerVarInt($buffer, $offset);
			$this->username = substr($buffer, $offset, $usernameLength);
			$this->plugin->getLogger()->info("Java Edition player '{$this->username}' is connecting from {$this->address}:{$this->port}");
			$this->completeLogin();
		}
	}

	private function completeLogin(): void
	{
		$this->uuid = $this->generateOfflineUUID($this->username);
		$this->interface->setCompression($this->identifier);
		$this->sendLoginSuccess();
		$this->status = 1;

		// Send Join Game
		$this->sendJoinGame();

		// Send plugin message (brand)
		$this->sendPluginMessage("MC|Brand", "BigBrother");

		// Send difficulty
		$this->sendDifficulty(0);

		// Send spawn position
		$this->sendSpawnPosition(0, 64, 0);

		// Send player abilities
		$this->sendPlayerAbilities();

		// Send chunks around spawn (5x5 area)
		for ($x = -2; $x <= 2; $x++) {
			for ($z = -2; $z <= 2; $z++) {
				$this->sendChunk($x, $z);
			}
		}

		// Send player position AFTER chunks (y=65 to be on top of floor at y=64)
		$this->sendPlayerPositionAndLook(8.0, 65.0, 8.0, 0.0, 0.0);

		// Send keep alive
		$this->sendKeepAlive();

		$this->plugin->getLogger()->info("Java Edition player '{$this->username}' joined the game");
	}

	private function generateOfflineUUID(string $username): string
	{
		$hash = md5("OfflinePlayer:" . $username);
		return substr($hash, 0, 8) . "-" . substr($hash, 8, 4) . "-" . substr($hash, 12, 4) . "-" . substr($hash, 16, 4) . "-" . substr($hash, 20, 12);
	}

	private function sendLoginSuccess(): void
	{
		$packet = Binary::writeComputerVarInt(0x02);
		$packet .= Binary::writeComputerVarInt(strlen($this->uuid)) . $this->uuid;
		$packet .= Binary::writeComputerVarInt(strlen($this->username)) . $this->username;
		$this->sendPacket($packet);
	}

	public function sendPacket(string $data): void
	{
		$this->interface->sendRaw($this->identifier, $data);
	}

	private function sendJoinGame(): void
	{
		$packet = Binary::writeComputerVarInt(0x23);
		$packet .= Binary::writeInt(1); // Entity ID
		$packet .= chr(1); // Game mode (creative for flying)
		$packet .= Binary::writeInt(0); // Dimension
		$packet .= chr(0); // Difficulty
		$packet .= chr(20); // Max players
		$packet .= Binary::writeComputerVarInt(4) . "flat"; // Level type
		$packet .= chr(0); // Reduced debug
		$this->sendPacket($packet);
	}

	private function sendPluginMessage(string $channel, string $data): void
	{
		$packet = Binary::writeComputerVarInt(0x18);
		$packet .= Binary::writeComputerVarInt(strlen($channel)) . $channel;
		$packet .= $data;
		$this->sendPacket($packet);
	}

	private function sendDifficulty(int $difficulty): void
	{
		$packet = Binary::writeComputerVarInt(0x0D);
		$packet .= chr($difficulty);
		$this->sendPacket($packet);
	}

	private function sendSpawnPosition(int $x, int $y, int $z): void
	{
		$packet = Binary::writeComputerVarInt(0x46);
		$position = (($x & 0x3FFFFFF) << 38) | (($z & 0x3FFFFFF) << 12) | ($y & 0xFFF);
		$packet .= Binary::writeLong($position);
		$this->sendPacket($packet);
	}

	private function sendPlayerAbilities(): void
	{
		$packet = Binary::writeComputerVarInt(0x2C);
		$packet .= chr(0x0F); // Flags: invulnerable, flying, allow flying, creative
		$packet .= pack('G', 0.05); // Flying speed
		$packet .= pack('G', 0.1); // Walking speed
		$this->sendPacket($packet);
	}

	private function sendChunk(int $chunkX, int $chunkZ): void
	{
		$packet = Binary::writeComputerVarInt(0x20); // Chunk Data packet
		$packet .= Binary::writeInt($chunkX);
		$packet .= Binary::writeInt($chunkZ);
		$packet .= chr(1); // Ground-up continuous (full chunk)
		$packet .= Binary::writeComputerVarInt(0x10); // Primary bit mask - section 4 (y=64-79)

		// Build chunk section at y=64
		$section = $this->buildChunkSection();

		// Biomes (256 bytes)
		$section .= str_repeat(chr(1), 256);

		$packet .= Binary::writeComputerVarInt(strlen($section));
		$packet .= $section;

		// Block entities
		$packet .= Binary::writeComputerVarInt(0);

		$this->sendPacket($packet);
	}

	private function buildChunkSection(): string
	{
		$section = "";

		// Use 4 bits per block with palette
		$section .= chr(4);

		// Palette: 2 entries (air=0, stone=1)
		$section .= Binary::writeComputerVarInt(2);
		$section .= Binary::writeComputerVarInt(0); // air
		$section .= Binary::writeComputerVarInt(1); // stone

		// Data array: 4096 blocks * 4 bits / 64 bits = 256 longs
		$section .= Binary::writeComputerVarInt(256);

		// Block order in 1.12.2: (y * 16 + z) * 16 + x = y*256 + z*16 + x
		// y=0 means indices 0-255
		// Each long = 16 blocks * 4 bits = 64 bits
		// 256 blocks / 16 blocks per long = 16 longs for y=0

		// Write 256 longs as raw bytes
		for ($longIdx = 0; $longIdx < 256; $longIdx++) {
			$startBlock = $longIdx * 16;
			$yLevel = intdiv($startBlock, 256);

			if ($yLevel == 0) {
				// Y=0 layer: stone (palette index 1)
				// 16 blocks with palette index 1 at 4 bits each
				// Each byte = 2 blocks: 0x11
				$section .= "\x11\x11\x11\x11\x11\x11\x11\x11";
			} else {
				// Air (palette index 0)
				$section .= "\x00\x00\x00\x00\x00\x00\x00\x00";
			}
		}

		// Block light (2048 bytes) - full brightness
		$section .= str_repeat("\xFF", 2048);

		// Sky light (2048 bytes) - full brightness
		$section .= str_repeat("\xFF", 2048);

		return $section;
	}


	private function sendPlayerPositionAndLook(float $x, float $y, float $z, float $yaw, float $pitch): void
	{
		static $teleportId = 0;
		$teleportId++;

		$packet = Binary::writeComputerVarInt(0x2F);
		$packet .= pack('E', $x);
		$packet .= pack('E', $y);
		$packet .= pack('E', $z);
		$packet .= pack('G', $yaw);
		$packet .= pack('G', $pitch);
		$packet .= chr(0);
		$packet .= Binary::writeComputerVarInt($teleportId);
		$this->sendPacket($packet);
	}

	private function sendKeepAlive(): void
	{
		$this->lastKeepAlive = time();
		$packet = Binary::writeComputerVarInt(0x1F);
		$packet .= Binary::writeLong($this->lastKeepAlive);
		$this->sendPacket($packet);
	}

	private function handlePlayPacket(int $pid, string $buffer, int $offset): void
	{
		switch ($pid) {
			case 0x00: // Teleport Confirm
				break;
			case 0x0B: // Keep Alive response
				// Send another keep alive
				$this->sendKeepAlive();
				break;
			case 0x02: // Chat
				$len = Binary::readComputerVarInt($buffer, $offset);
				$msg = substr($buffer, $offset, $len);
				$this->plugin->getLogger()->info("[Java Chat] {$this->username}: $msg");
				// Echo the message back to the Java player
				$this->sendChatMessage("<" . $this->username . "> " . $msg);
				// Broadcast to Bedrock players
				$this->plugin->broadcastJavaChat($this->username, $msg);
				break;
		}
	}

	public function sendChatMessage(string $message): void
	{
		// Chat Message packet (0x0F for 1.12.2)
		$packet = Binary::writeComputerVarInt(0x0F);
		// JSON chat format
		$json = json_encode(["text" => $message]);
		$packet .= Binary::writeComputerVarInt(strlen($json)) . $json;
		$packet .= chr(0); // Position: 0 = chat box
		$this->sendPacket($packet);
	}

	public function close(string $reason = ""): void
	{
		if ($reason !== "") {
			$this->plugin->getLogger()->info("Java Edition player '{$this->username}' disconnected: $reason");
		}
		$this->interface->closeSessionFromMain($this->identifier);
	}

	public function getIdentifier(): int { return $this->identifier; }

	public function getUsername(): string { return $this->username; }

	public function getAddress(): string { return $this->address; }

	public function getPort(): int { return $this->port; }
}
