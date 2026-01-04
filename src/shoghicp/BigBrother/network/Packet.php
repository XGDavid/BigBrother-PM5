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
 * Base class for Java Edition packets
 */
abstract class Packet
{

	/**
	 * Read packet data from buffer
	 *
	 * @param string $buffer
	 * @param int $offset
	 */
	abstract public function read(string $buffer, int &$offset): void;

	/**
	 * Write packet data to buffer
	 *
	 * @return string
	 */
	abstract public function write(): string;

	/**
	 * Helper to write packet with ID prefix
	 *
	 * @return string
	 */
	protected function writePacket(): string
	{
		return Binary::writeComputerVarInt($this->pid());
	}

	/**
	 * Get the packet ID
	 *
	 * @return int
	 */
	abstract public function pid(): int;
}

