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

use shoghicp\BigBrother\utils\Binary;

/**
 * Base class for client-to-server (inbound) packets
 * Packet IDs are for Minecraft Java Edition 1.21.4 (Protocol 769)
 */
abstract class InboundPacket extends Packet
{

	// Play state packets (1.21.4 - client to server)
	public const CONFIRM_TELEPORTATION_PACKET = 0x00;
	public const QUERY_BLOCK_ENTITY_TAG_PACKET = 0x01;
	public const CHANGE_DIFFICULTY_PACKET = 0x02;
	public const CHAT_COMMAND_PACKET = 0x03;
	public const SIGNED_CHAT_COMMAND_PACKET = 0x04;
	public const CHAT_MESSAGE_PACKET = 0x05;
	public const PLAYER_SESSION_PACKET = 0x06;
	public const CHUNK_BATCH_RECEIVED_PACKET = 0x07;
	public const CLIENT_INFORMATION_PACKET = 0x08;
	public const COMMAND_SUGGESTIONS_REQUEST_PACKET = 0x09;
	public const CONFIGURATION_ACKNOWLEDGED_PACKET = 0x0A;
	public const CLICK_CONTAINER_BUTTON_PACKET = 0x0B;
	public const CLICK_CONTAINER_PACKET = 0x0C;
	public const CLOSE_CONTAINER_PACKET = 0x0D;
	public const CHANGE_CONTAINER_SLOT_STATE_PACKET = 0x0E;
	public const COOKIE_RESPONSE_PACKET = 0x0F;
	public const PLUGIN_MESSAGE_PACKET = 0x10;
	public const DEBUG_SAMPLE_SUBSCRIPTION_PACKET = 0x11;
	public const EDIT_BOOK_PACKET = 0x12;
	public const QUERY_ENTITY_TAG_PACKET = 0x13;
	public const INTERACT_PACKET = 0x14;
	public const JIGSAW_GENERATE_PACKET = 0x15;
	public const KEEP_ALIVE_PACKET = 0x16;
	public const LOCK_DIFFICULTY_PACKET = 0x17;
	public const SET_PLAYER_POSITION_PACKET = 0x18;
	public const SET_PLAYER_POSITION_ROTATION_PACKET = 0x19;
	public const SET_PLAYER_ROTATION_PACKET = 0x1A;
	public const SET_PLAYER_ON_GROUND_PACKET = 0x1B;
	public const MOVE_VEHICLE_PACKET = 0x1C;
	public const PADDLE_BOAT_PACKET = 0x1D;
	public const PICK_ITEM_PACKET = 0x1E;
	public const PING_REQUEST_PACKET = 0x1F;
	public const PLACE_RECIPE_PACKET = 0x20;
	public const PLAYER_ABILITIES_PACKET = 0x21;
	public const PLAYER_ACTION_PACKET = 0x22;
	public const PLAYER_COMMAND_PACKET = 0x23;
	public const PLAYER_INPUT_PACKET = 0x24;
	public const PONG_PACKET = 0x25;
	public const CHANGE_RECIPE_BOOK_SETTINGS_PACKET = 0x26;
	public const SET_SEEN_RECIPE_PACKET = 0x27;
	public const RENAME_ITEM_PACKET = 0x28;
	public const RESOURCE_PACK_STATUS_PACKET = 0x29;
	public const SEEN_ADVANCEMENTS_PACKET = 0x2A;
	public const SELECT_TRADE_PACKET = 0x2B;
	public const SET_BEACON_EFFECT_PACKET = 0x2C;
	public const SET_HELD_ITEM_PACKET = 0x2D;
	public const PROGRAM_COMMAND_BLOCK_PACKET = 0x2E;
	public const PROGRAM_COMMAND_BLOCK_MINECART_PACKET = 0x2F;
	public const SET_CREATIVE_MODE_SLOT_PACKET = 0x30;
	public const PROGRAM_JIGSAW_BLOCK_PACKET = 0x31;
	public const PROGRAM_STRUCTURE_BLOCK_PACKET = 0x32;
	public const UPDATE_SIGN_PACKET = 0x33;
	public const SWING_ARM_PACKET = 0x34;
	public const TELEPORT_TO_ENTITY_PACKET = 0x35;
	public const USE_ITEM_ON_PACKET = 0x36;
	public const USE_ITEM_PACKET = 0x37;

	/** @var string */
	protected string $buffer = "";

	/** @var int */
	protected int $offset = 0;

	/**
	 * @inheritDoc
	 */
	public function read(string $buffer, int &$offset): void
	{
		$this->buffer = $buffer;
		$this->offset = $offset;
		$this->decode();
		$offset = $this->offset;
	}

	/**
	 * Decode the packet from buffer
	 */
	abstract protected function decode(): void;

	/**
	 * @inheritDoc
	 */
	public function write(): string
	{
		// Inbound packets don't need to be written
		return "";
	}

	/**
	 * Read a boolean
	 *
	 * @return bool
	 */
	protected function getBool(): bool
	{
		return $this->getByte() !== 0;
	}

	/**
	 * Read a byte
	 *
	 * @return int
	 */
	protected function getByte(): int
	{
		return ord($this->buffer[$this->offset++]);
	}

	/**
	 * Read a short (big-endian)
	 *
	 * @return int
	 */
	protected function getShort(): int
	{
		$val = Binary::readShort(substr($this->buffer, $this->offset, 2));
		$this->offset += 2;
		return $val;
	}

	/**
	 * Read an int (big-endian)
	 *
	 * @return int
	 */
	protected function getInt(): int
	{
		$val = Binary::readInt(substr($this->buffer, $this->offset, 4));
		$this->offset += 4;
		return $val;
	}

	/**
	 * Read a long (big-endian)
	 *
	 * @return int
	 */
	protected function getLong(): int
	{
		$val = Binary::readLong(substr($this->buffer, $this->offset, 8));
		$this->offset += 8;
		return $val;
	}

	/**
	 * Read a float
	 *
	 * @return float
	 */
	protected function getFloat(): float
	{
		$val = Binary::readFloat(substr($this->buffer, $this->offset, 4));
		$this->offset += 4;
		return $val;
	}

	/**
	 * Read a double
	 *
	 * @return float
	 */
	protected function getDouble(): float
	{
		$val = Binary::readDouble(substr($this->buffer, $this->offset, 8));
		$this->offset += 8;
		return $val;
	}

	/**
	 * Read a string with VarInt length prefix
	 *
	 * @return string
	 */
	protected function getString(): string
	{
		$length = $this->getVarInt();
		$str = substr($this->buffer, $this->offset, $length);
		$this->offset += $length;
		return $str;
	}

	/**
	 * Read a VarInt
	 *
	 * @return int
	 */
	protected function getVarInt(): int
	{
		return Binary::readComputerVarInt($this->buffer, $this->offset);
	}

	/**
	 * Read a UUID (16 bytes)
	 *
	 * @return string Hyphenated UUID string
	 */
	protected function getUUID(): string
	{
		$hex = bin2hex($this->get(16));
		return substr($hex, 0, 8) . "-" .
			substr($hex, 8, 4) . "-" .
			substr($hex, 12, 4) . "-" .
			substr($hex, 16, 4) . "-" .
			substr($hex, 20, 12);
	}

	/**
	 * Read raw bytes
	 *
	 * @param int $length
	 * @return string
	 */
	protected function get(int $length): string
	{
		$data = substr($this->buffer, $this->offset, $length);
		$this->offset += $length;
		return $data;
	}

	/**
	 * Read a position from packed long
	 *
	 * @return array{int, int, int}
	 */
	protected function getPosition(): array
	{
		return Binary::readPosition($this->buffer, $this->offset);
	}
}

