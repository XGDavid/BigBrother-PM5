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

namespace shoghicp\BigBrother\utils;

/**
 * Utility class for converting between Bedrock and Java Edition data formats
 */
class ConvertUtils
{

	/** @var array<string, int> */
	private static array $blockStateMap = [];

	/** @var bool */
	private static bool $initialized = false;

	/**
	 * Initialize conversion utilities
	 */
	public static function init(): void
	{
		if (self::$initialized) {
			return;
		}
		self::$initialized = true;
	}

	/**
	 * Load block state mapping from JSON file
	 *
	 * @param string $path
	 */
	public static function loadBlockStateIndex(string $path): void
	{
		if (!file_exists($path)) {
			return;
		}

		$content = file_get_contents($path);
		if ($content === false) {
			return;
		}

		$data = json_decode($content, true);
		if (is_array($data)) {
			self::$blockStateMap = $data;
		}
	}

	/**
	 * Convert Bedrock block state to Java block state ID
	 *
	 * @param int $bedrockId
	 * @param int $bedrockMeta
	 * @return int
	 */
	public static function convertBlockState(int $bedrockId, int $bedrockMeta = 0): int
	{
		$key = $bedrockId . ":" . $bedrockMeta;
		return self::$blockStateMap[$key] ?? 0;
	}

	/**
	 * Convert Java block state ID to Bedrock block state
	 *
	 * @param int $javaStateId
	 * @return array{int, int}
	 */
	public static function convertJavaBlockState(int $javaStateId): array
	{
		foreach (self::$blockStateMap as $key => $value) {
			if ($value === $javaStateId) {
				$parts = explode(":", $key);
				return [(int)$parts[0], (int)($parts[1] ?? 0)];
			}
		}
		return [0, 0];
	}

	/**
	 * Convert entity type ID between editions
	 *
	 * @param string $bedrockType
	 * @return int
	 */
	public static function convertEntityType(string $bedrockType): int
	{
		// Map of Bedrock entity types to Java entity type IDs
		// https://wiki.vg/Entity_metadata#Mobs
		$typeMap = [
			"minecraft:player" => 128,
			"minecraft:item" => 54,
			"minecraft:xp_orb" => 24,
			"minecraft:arrow" => 2,
			"minecraft:snowball" => 83,
			"minecraft:egg" => 87,
			"minecraft:fireball" => 63,
			"minecraft:small_fireball" => 101,
			"minecraft:ender_pearl" => 88,
			"minecraft:wither_skull" => 27,
			"minecraft:falling_block" => 26,
			"minecraft:tnt" => 59,
			"minecraft:armor_stand" => 1,
			"minecraft:boat" => 6,
			"minecraft:minecart" => 67,
			"minecraft:creeper" => 17,
			"minecraft:skeleton" => 80,
			"minecraft:spider" => 85,
			"minecraft:zombie" => 116,
			"minecraft:slime" => 81,
			"minecraft:ghast" => 28,
			"minecraft:pig_zombie" => 117, // zombified_piglin
			"minecraft:enderman" => 23,
			"minecraft:cave_spider" => 12,
			"minecraft:silverfish" => 79,
			"minecraft:blaze" => 4,
			"minecraft:magma_cube" => 62,
			"minecraft:ender_dragon" => 22,
			"minecraft:wither" => 114,
			"minecraft:bat" => 3,
			"minecraft:witch" => 113,
			"minecraft:pig" => 73,
			"minecraft:sheep" => 78,
			"minecraft:cow" => 16,
			"minecraft:chicken" => 13,
			"minecraft:squid" => 86,
			"minecraft:wolf" => 115,
			"minecraft:mooshroom" => 70,
			"minecraft:ocelot" => 71,
			"minecraft:iron_golem" => 55,
			"minecraft:horse" => 51,
			"minecraft:rabbit" => 76,
			"minecraft:villager" => 110,
		];

		return $typeMap[$bedrockType] ?? 0;
	}

	/**
	 * Convert game mode between editions
	 *
	 * @param int $bedrockMode
	 * @return int
	 */
	public static function convertGameMode(int $bedrockMode): int
	{
		// Both editions use the same values: 0=Survival, 1=Creative, 2=Adventure, 3=Spectator
		return $bedrockMode;
	}

	/**
	 * Convert dimension ID
	 *
	 * @param int $bedrockDimension
	 * @return string
	 */
	public static function convertDimension(int $bedrockDimension): string
	{
		return match ($bedrockDimension) {
			0 => "minecraft:overworld",
			1 => "minecraft:the_nether",
			2 => "minecraft:the_end",
			default => "minecraft:overworld",
		};
	}

	/**
	 * Convert particle type
	 *
	 * @param int $bedrockParticle
	 * @return int
	 */
	public static function convertParticle(int $bedrockParticle): int
	{
		// TODO: Implement particle mapping
		return 0;
	}

	/**
	 * Convert sound event
	 *
	 * @param string $bedrockSound
	 * @return string
	 */
	public static function convertSound(string $bedrockSound): string
	{
		// Most sounds have the same ID in both editions
		return $bedrockSound;
	}

	/**
	 * Convert color format from Bedrock to Java
	 *
	 * @param int $bedrockColor
	 * @return int
	 */
	public static function convertColor(int $bedrockColor): int
	{
		// Bedrock uses ABGR, Java uses ARGB
		$a = ($bedrockColor >> 24) & 0xFF;
		$b = ($bedrockColor >> 16) & 0xFF;
		$g = ($bedrockColor >> 8) & 0xFF;
		$r = $bedrockColor & 0xFF;
		return ($a << 24) | ($r << 16) | ($g << 8) | $b;
	}

	/**
	 * Convert skin data (placeholder - skin conversion is complex)
	 *
	 * @param string $bedrockSkin
	 * @return string
	 */
	public static function convertSkin(string $bedrockSkin): string
	{
		// TODO: Implement proper skin conversion
		// Java Edition uses different skin format
		return "";
	}
}

