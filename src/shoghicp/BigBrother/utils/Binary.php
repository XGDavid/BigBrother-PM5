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

use shoghicp\BigBrother\network\Session;

class Binary extends \pocketmine\utils\Binary
{

	/**
	 * @param string $input
	 * @return string
	 */
	public static function sha1(string $input): string
	{
		$hash = sha1($input, true);
		$negative = (ord($hash[0]) & 0x80) !== 0;

		if ($negative) {
			// Two's complement for negative numbers
			$hash = ~$hash;
			$carry = 1;
			for ($i = strlen($hash) - 1; $i >= 0; $i--) {
				$val = ord($hash[$i]) + $carry;
				$hash[$i] = chr($val & 0xff);
				$carry = $val >> 8;
			}
		}

		$hex = ltrim(bin2hex($hash), "0");
		return ($negative ? "-" : "") . ($hex === "" ? "0" : $hex);
	}

	/**
	 * @param string $uuid
	 * @return string
	 */
	public static function UUIDtoString(string $uuid): string
	{
		return substr($uuid, 0, 8) . "-" . substr($uuid, 8, 4) . "-" . substr($uuid, 12, 4) . "-" . substr($uuid, 16, 4) . "-" . substr($uuid, 20);
	}

	/**
	 * Debug helper to show hex entities
	 *
	 * @param string $str
	 * @return string
	 */
	public static function hexentities(string $str): string
	{
		$return = '';
		for ($i = 0, $iMax = strlen($str); $i < $iMax; $i++) {
			$return .= '&#x' . bin2hex(substr($str, $i, 1)) . ';';
		}
		return $return;
	}

	/**
	 * @param Session $session
	 * @param int &$offset
	 * @return int|false|null int on success, false if connection closed, null if no data available
	 */
	public static function readVarIntSession(Session $session, int &$offset = 0): int|false|null
	{
		$number = 0;
		$shift = 0;

		while (true) {
			$b = $session->read(1);
			if ($b === false) {
				return false; // Connection closed
			}
			if ($b === "") {
				if ($shift === 0) {
					return null; // No data available yet
				}
				return false; // Incomplete varint, connection issue
			}
			$c = ord($b);
			$number |= ($c & 0x7f) << $shift;
			$shift += 7;
			++$offset;
			if (($c & 0x80) === 0x00) {
				break;
			}
		}
		return $number;
	}

	/**
	 * @param resource $fp
	 * @param int &$offset
	 * @return int|false
	 */
	public static function readVarIntStream($fp, int &$offset = 0): int|false
	{
		$number = 0;
		$shift = 0;

		while (true) {
			$b = fgetc($fp);
			if ($b === false) {
				return false;
			}
			$c = ord($b);
			$number |= ($c & 0x7f) << $shift;
			$shift += 7;
			++$offset;
			if (($c & 0x80) === 0x00) {
				break;
			}
		}
		return $number;
	}

	/**
	 * Write a VarLong (up to 64 bits)
	 *
	 * @param int $number
	 * @return string
	 */
	public static function writeComputerVarLong(int $number): string
	{
		$encoded = "";
		$count = 0;
		do {
			$next_byte = $number & 0x7f;
			$number >>= 7;

			if ($number > 0) {
				$next_byte |= 0x80;
			}

			$encoded .= chr($next_byte);
			$count++;
		} while ($number > 0 && $count < 10);

		return $encoded;
	}

	/**
	 * @param bool $value
	 * @return string
	 */
	public static function writeBool(bool $value): string
	{
		return chr($value ? 1 : 0);
	}

	/**
	 * @param string $buffer
	 * @param int &$offset
	 * @return bool
	 */
	public static function readBoolWithOffset(string $buffer, int &$offset): bool
	{
		return ord($buffer[$offset++]) !== 0;
	}

	/**
	 * Write position as packed long (x: 26 bits, z: 26 bits, y: 12 bits)
	 *
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return string
	 */
	public static function writePosition(int $x, int $y, int $z): string
	{
		$val = (($x & 0x3FFFFFF) << 38) | (($z & 0x3FFFFFF) << 12) | ($y & 0xFFF);
		return self::writeLong($val);
	}

	/**
	 * Read position from packed long
	 *
	 * @param string $buffer
	 * @param int &$offset
	 * @return array{int, int, int}
	 */
	public static function readPosition(string $buffer, int &$offset): array
	{
		$val = self::readLong(substr($buffer, $offset, 8));
		$offset += 8;

		$x = $val >> 38;
		$z = ($val >> 12) & 0x3FFFFFF;
		$y = $val & 0xFFF;

		// Sign extend
		if ($x >= 0x2000000) $x -= 0x4000000;
		if ($z >= 0x2000000) $z -= 0x4000000;

		return [$x, $y, $z];
	}

	/**
	 * Write a string with VarInt length prefix
	 *
	 * @param string $str
	 * @return string
	 */
	public static function writeString(string $str): string
	{
		return self::writeComputerVarInt(strlen($str)) . $str;
	}

	/**
	 * @param int $number
	 * @return string
	 */
	public static function writeComputerVarInt(int $number): string
	{
		$encoded = "";
		do {
			$next_byte = $number & 0x7f;
			$number >>= 7;

			if ($number > 0) {
				$next_byte |= 0x80;
			}

			$encoded .= chr($next_byte);
		} while ($number > 0);

		return $encoded;
	}

	/**
	 * Read a string with VarInt length prefix
	 *
	 * @param string $buffer
	 * @param int &$offset
	 * @return string
	 */
	public static function readString(string $buffer, int &$offset): string
	{
		$length = self::readComputerVarInt($buffer, $offset);
		$str = substr($buffer, $offset, $length);
		$offset += $length;
		return $str;
	}

	/**
	 * @param string $buffer
	 * @param int &$offset
	 * @return int
	 */
	public static function readComputerVarInt(string $buffer, int &$offset = 0): int
	{
		$number = 0;
		$shift = 0;

		while (true) {
			if ($offset >= strlen($buffer)) {
				break;
			}
			$c = ord($buffer[$offset++]);
			$number |= ($c & 0x7f) << $shift;
			$shift += 7;
			if (($c & 0x80) === 0x00) {
				break;
			}
		}
		return $number;
	}
}

