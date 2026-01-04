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
 * Base class for server-to-client (outbound) packets
 * Packet IDs are for Minecraft Java Edition 1.21.4 (Protocol 769)
 */
abstract class OutboundPacket extends Packet
{

	// Login state packets (0x00 - 0x04)
	public const LOGIN_DISCONNECT_PACKET = 0x00;
	public const ENCRYPTION_REQUEST_PACKET = 0x01;
	public const LOGIN_SUCCESS_PACKET = 0x02;
	public const SET_COMPRESSION_PACKET = 0x03;
	public const LOGIN_PLUGIN_REQUEST_PACKET = 0x04;

	// Configuration state packets (after login)
	public const FINISH_CONFIGURATION_PACKET = 0x03;

	// Play state packets (1.21.4)
	public const BUNDLE_DELIMITER_PACKET = 0x00;
	public const SPAWN_ENTITY_PACKET = 0x01;
	public const SPAWN_EXPERIENCE_ORB_PACKET = 0x02;
	public const ENTITY_ANIMATION_PACKET = 0x03;
	public const AWARD_STATISTICS_PACKET = 0x04;
	public const ACKNOWLEDGE_BLOCK_CHANGE_PACKET = 0x05;
	public const BLOCK_DESTROY_STAGE_PACKET = 0x06;
	public const BLOCK_ENTITY_DATA_PACKET = 0x07;
	public const BLOCK_ACTION_PACKET = 0x08;
	public const BLOCK_UPDATE_PACKET = 0x09;
	public const BOSS_BAR_PACKET = 0x0A;
	public const CHANGE_DIFFICULTY_PACKET = 0x0B;
	public const CHUNK_BATCH_FINISHED_PACKET = 0x0C;
	public const CHUNK_BATCH_START_PACKET = 0x0D;
	public const CHUNK_BIOMES_PACKET = 0x0E;
	public const CLEAR_TITLES_PACKET = 0x0F;
	public const COMMAND_SUGGESTIONS_PACKET = 0x10;
	public const COMMANDS_PACKET = 0x11;
	public const CLOSE_CONTAINER_PACKET = 0x12;
	public const SET_CONTAINER_CONTENT_PACKET = 0x13;
	public const SET_CONTAINER_PROPERTY_PACKET = 0x14;
	public const SET_CONTAINER_SLOT_PACKET = 0x15;
	public const COOKIE_REQUEST_PACKET = 0x16;
	public const SET_COOLDOWN_PACKET = 0x17;
	public const CHAT_SUGGESTIONS_PACKET = 0x18;
	public const PLUGIN_MESSAGE_PACKET = 0x19;
	public const DAMAGE_EVENT_PACKET = 0x1A;
	public const DEBUG_SAMPLE_PACKET = 0x1B;
	public const DELETE_MESSAGE_PACKET = 0x1C;
	public const DISCONNECT_PACKET = 0x1D;
	public const DISGUISED_CHAT_PACKET = 0x1E;
	public const ENTITY_EVENT_PACKET = 0x1F;
	public const EXPLOSION_PACKET = 0x20;
	public const UNLOAD_CHUNK_PACKET = 0x21;
	public const GAME_EVENT_PACKET = 0x22;
	public const OPEN_HORSE_SCREEN_PACKET = 0x23;
	public const HURT_ANIMATION_PACKET = 0x24;
	public const INITIALIZE_WORLD_BORDER_PACKET = 0x25;
	public const KEEP_ALIVE_PACKET = 0x26;
	public const CHUNK_DATA_PACKET = 0x27;
	public const WORLD_EVENT_PACKET = 0x28;
	public const PARTICLE_PACKET = 0x29;
	public const UPDATE_LIGHT_PACKET = 0x2A;
	public const LOGIN_PACKET = 0x2B;
	public const MAP_DATA_PACKET = 0x2C;
	public const MERCHANT_OFFERS_PACKET = 0x2D;
	public const UPDATE_ENTITY_POSITION_PACKET = 0x2E;
	public const UPDATE_ENTITY_POSITION_ROTATION_PACKET = 0x2F;
	public const UPDATE_ENTITY_ROTATION_PACKET = 0x30;
	public const MOVE_VEHICLE_PACKET = 0x31;
	public const OPEN_BOOK_PACKET = 0x32;
	public const OPEN_SCREEN_PACKET = 0x33;
	public const OPEN_SIGN_EDITOR_PACKET = 0x34;
	public const PING_PACKET = 0x35;
	public const PONG_RESPONSE_PACKET = 0x36;
	public const PLACE_GHOST_RECIPE_PACKET = 0x37;
	public const PLAYER_ABILITIES_PACKET = 0x38;
	public const PLAYER_CHAT_PACKET = 0x39;
	public const END_COMBAT_PACKET = 0x3A;
	public const ENTER_COMBAT_PACKET = 0x3B;
	public const COMBAT_DEATH_PACKET = 0x3C;
	public const PLAYER_INFO_REMOVE_PACKET = 0x3D;
	public const PLAYER_INFO_UPDATE_PACKET = 0x3E;
	public const LOOK_AT_PACKET = 0x3F;
	public const SYNCHRONIZE_PLAYER_POSITION_PACKET = 0x40;
	public const UPDATE_RECIPE_BOOK_PACKET = 0x41;
	public const REMOVE_ENTITIES_PACKET = 0x42;
	public const REMOVE_ENTITY_EFFECT_PACKET = 0x43;
	public const RESET_SCORE_PACKET = 0x44;
	public const REMOVE_RESOURCE_PACK_PACKET = 0x45;
	public const ADD_RESOURCE_PACK_PACKET = 0x46;
	public const RESPAWN_PACKET = 0x47;
	public const SET_HEAD_ROTATION_PACKET = 0x48;
	public const UPDATE_SECTION_BLOCKS_PACKET = 0x49;
	public const SELECT_ADVANCEMENTS_TAB_PACKET = 0x4A;
	public const SERVER_DATA_PACKET = 0x4B;
	public const SET_ACTION_BAR_TEXT_PACKET = 0x4C;
	public const SET_BORDER_CENTER_PACKET = 0x4D;
	public const SET_BORDER_LERP_SIZE_PACKET = 0x4E;
	public const SET_BORDER_SIZE_PACKET = 0x4F;
	public const SET_BORDER_WARNING_DELAY_PACKET = 0x50;
	public const SET_BORDER_WARNING_DISTANCE_PACKET = 0x51;
	public const SET_CAMERA_PACKET = 0x52;
	public const SET_HELD_ITEM_PACKET = 0x53;
	public const SET_CENTER_CHUNK_PACKET = 0x54;
	public const SET_RENDER_DISTANCE_PACKET = 0x55;
	public const SET_DEFAULT_SPAWN_POSITION_PACKET = 0x56;
	public const DISPLAY_OBJECTIVE_PACKET = 0x57;
	public const SET_ENTITY_METADATA_PACKET = 0x58;
	public const LINK_ENTITIES_PACKET = 0x59;
	public const SET_ENTITY_VELOCITY_PACKET = 0x5A;
	public const SET_EQUIPMENT_PACKET = 0x5B;
	public const SET_EXPERIENCE_PACKET = 0x5C;
	public const SET_HEALTH_PACKET = 0x5D;
	public const UPDATE_OBJECTIVES_PACKET = 0x5E;
	public const SET_PASSENGERS_PACKET = 0x5F;
	public const UPDATE_TEAMS_PACKET = 0x60;
	public const UPDATE_SCORE_PACKET = 0x61;
	public const SET_SIMULATION_DISTANCE_PACKET = 0x62;
	public const SET_SUBTITLE_TEXT_PACKET = 0x63;
	public const UPDATE_TIME_PACKET = 0x64;
	public const SET_TITLE_TEXT_PACKET = 0x65;
	public const SET_TITLE_ANIMATION_TIMES_PACKET = 0x66;
	public const ENTITY_SOUND_EFFECT_PACKET = 0x67;
	public const SOUND_EFFECT_PACKET = 0x68;
	public const START_CONFIGURATION_PACKET = 0x69;
	public const STOP_SOUND_PACKET = 0x6A;
	public const STORE_COOKIE_PACKET = 0x6B;
	public const SYSTEM_CHAT_PACKET = 0x6C;
	public const SET_TAB_LIST_HEADER_FOOTER_PACKET = 0x6D;
	public const TAG_QUERY_PACKET = 0x6E;
	public const PICKUP_ITEM_PACKET = 0x6F;
	public const TELEPORT_ENTITY_PACKET = 0x70;
	public const SET_TICKING_STATE_PACKET = 0x71;
	public const STEP_TICK_PACKET = 0x72;
	public const TRANSFER_PACKET = 0x73;
	public const UPDATE_ADVANCEMENTS_PACKET = 0x74;
	public const UPDATE_ATTRIBUTES_PACKET = 0x75;
	public const ENTITY_EFFECT_PACKET = 0x76;
	public const UPDATE_RECIPES_PACKET = 0x77;
	public const UPDATE_TAGS_PACKET = 0x78;
	public const PROJECTILE_POWER_PACKET = 0x79;
	public const CUSTOM_REPORT_DETAILS_PACKET = 0x7A;
	public const SERVER_LINKS_PACKET = 0x7B;

	/** @var string */
	protected string $buffer = "";

	/**
	 * @inheritDoc
	 */
	public function read(string $buffer, int &$offset): void
	{
		// Outbound packets don't need to be read
	}

	/**
	 * @inheritDoc
	 */
	public function write(): string
	{
		$this->buffer = "";
		$this->buffer .= Binary::writeComputerVarInt($this->pid());
		$this->encode();
		return $this->buffer;
	}

	/**
	 * Encode the packet data
	 */
	abstract protected function encode(): void;

	/**
	 * Write a byte
	 *
	 * @param int $v
	 */
	protected function putByte(int $v): void
	{
		$this->buffer .= chr($v);
	}

	/**
	 * Write a boolean
	 *
	 * @param bool $v
	 */
	protected function putBool(bool $v): void
	{
		$this->buffer .= chr($v ? 1 : 0);
	}

	/**
	 * Write a short (big-endian)
	 *
	 * @param int $v
	 */
	protected function putShort(int $v): void
	{
		$this->buffer .= Binary::writeShort($v);
	}

	/**
	 * Write an int (big-endian)
	 *
	 * @param int $v
	 */
	protected function putInt(int $v): void
	{
		$this->buffer .= Binary::writeInt($v);
	}

	/**
	 * Write a long (big-endian)
	 *
	 * @param int $v
	 */
	protected function putLong(int $v): void
	{
		$this->buffer .= Binary::writeLong($v);
	}

	/**
	 * Write a float
	 *
	 * @param float $v
	 */
	protected function putFloat(float $v): void
	{
		$this->buffer .= Binary::writeFloat($v);
	}

	/**
	 * Write a double
	 *
	 * @param float $v
	 */
	protected function putDouble(float $v): void
	{
		$this->buffer .= Binary::writeDouble($v);
	}

	/**
	 * Write a VarInt
	 *
	 * @param int $v
	 */
	protected function putVarInt(int $v): void
	{
		$this->buffer .= Binary::writeComputerVarInt($v);
	}

	/**
	 * Write a string with VarInt length prefix
	 *
	 * @param string $v
	 */
	protected function putString(string $v): void
	{
		$this->buffer .= Binary::writeComputerVarInt(strlen($v)) . $v;
	}

	/**
	 * Write raw bytes
	 *
	 * @param string $v
	 */
	protected function put(string $v): void
	{
		$this->buffer .= $v;
	}

	/**
	 * Write a UUID (16 bytes)
	 *
	 * @param string $uuid Hyphenated UUID string
	 */
	protected function putUUID(string $uuid): void
	{
		$this->buffer .= hex2bin(str_replace("-", "", $uuid));
	}

	/**
	 * Write a position as packed long
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 */
	protected function putPosition(int $x, int $y, int $z): void
	{
		$this->buffer .= Binary::writePosition($x, $y, $z);
	}
}

