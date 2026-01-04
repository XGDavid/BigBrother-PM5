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

namespace shoghicp\BigBrother;

use InvalidArgumentException;
use phpseclib3\Crypt\RSA;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use shoghicp\BigBrother\network\ProtocolInterface;
use shoghicp\BigBrother\network\ServerThread;
use shoghicp\BigBrother\network\Translator;
use shoghicp\BigBrother\utils\ConvertUtils;
use Throwable;

class BigBrother extends PluginBase implements Listener
{

	/** @var mixed RSA key object */
	protected mixed $rsaKey = null;
	/** @var string */
	protected string $privateKey = "";
	/** @var string */
	protected string $publicKey = "";
	/** @var bool */
	protected bool $onlineMode = false;
	/** @var Translator|null */
	protected ?Translator $translator = null;
	/** @var string */
	protected string $desktopPrefix = "PC_";
	/** @var array */
	protected array $profileCache = [];
	/** @var string */
	protected string $dimensionCodec = "";
	/** @var string */
	protected string $dimension = "";
	/** @var ProtocolInterface|null */
	private ?ProtocolInterface $interface = null;

	/**
	 * @param string|null $message
	 * @param int $type
	 * @param array|null $parameters
	 * @return string
	 */
	public static function toJSON(?string $message, int $type = 1, ?array $parameters = []): string
	{
		$result = json_decode(self::toJSONInternal($message ?? ""), true);

		if ($result === null) {
			return json_encode(["text" => $message ?? ""]);
		}

		if (isset($result["extra"]) && count($result["extra"]) === 0) {
			unset($result["extra"]);
		}

		return json_encode($result, JSON_UNESCAPED_SLASHES) ?: "{}";
	}

	/**
	 * Returns an JSON-formatted string with colors/markup
	 *
	 * @param string|string[] $string
	 * @return string
	 * @internal
	 */
	public static function toJSONInternal($string): string
	{
		if (!is_array($string)) {
			$string = TextFormat::tokenize($string);
		}
		$newString = [];
		$pointer = &$newString;
		$color = "white";
		$bold = false;
		$italic = false;
		$underlined = false;
		$strikethrough = false;
		$obfuscated = false;
		$index = 0;

		foreach ($string as $token) {
			if (isset($pointer["text"])) {
				if (!isset($newString["extra"])) {
					$newString["extra"] = [];
				}
				$newString["extra"][$index] = [];
				$pointer = &$newString["extra"][$index];
				if ($color !== "white") {
					$pointer["color"] = $color;
				}
				if ($bold) {
					$pointer["bold"] = true;
				}
				if ($italic) {
					$pointer["italic"] = true;
				}
				if ($underlined) {
					$pointer["underlined"] = true;
				}
				if ($strikethrough) {
					$pointer["strikethrough"] = true;
				}
				if ($obfuscated) {
					$pointer["obfuscated"] = true;
				}
				++$index;
			}
			switch ($token) {
				case TextFormat::BOLD:
					if (!$bold) {
						$pointer["bold"] = true;
						$bold = true;
					}
					break;
				case TextFormat::OBFUSCATED:
					if (!$obfuscated) {
						$pointer["obfuscated"] = true;
						$obfuscated = true;
					}
					break;
				case TextFormat::ITALIC:
					if (!$italic) {
						$pointer["italic"] = true;
						$italic = true;
					}
					break;
				case TextFormat::UNDERLINE:
					if (!$underlined) {
						$pointer["underlined"] = true;
						$underlined = true;
					}
					break;
				case TextFormat::STRIKETHROUGH:
					if (!$strikethrough) {
						$pointer["strikethrough"] = true;
						$strikethrough = true;
					}
					break;
				case TextFormat::RESET:
					if ($color !== "white") {
						$pointer["color"] = "white";
						$color = "white";
					}
					if ($bold) {
						$pointer["bold"] = false;
						$bold = false;
					}
					if ($italic) {
						$pointer["italic"] = false;
						$italic = false;
					}
					if ($underlined) {
						$pointer["underlined"] = false;
						$underlined = false;
					}
					if ($strikethrough) {
						$pointer["strikethrough"] = false;
						$strikethrough = false;
					}
					if ($obfuscated) {
						$pointer["obfuscated"] = false;
						$obfuscated = false;
					}
					break;

				// Colors
				case TextFormat::BLACK:
					$pointer["color"] = "black";
					$color = "black";
					break;
				case TextFormat::DARK_BLUE:
					$pointer["color"] = "dark_blue";
					$color = "dark_blue";
					break;
				case TextFormat::DARK_GREEN:
					$pointer["color"] = "dark_green";
					$color = "dark_green";
					break;
				case TextFormat::DARK_AQUA:
					$pointer["color"] = "dark_aqua";
					$color = "dark_aqua";
					break;
				case TextFormat::DARK_RED:
					$pointer["color"] = "dark_red";
					$color = "dark_red";
					break;
				case TextFormat::DARK_PURPLE:
					$pointer["color"] = "dark_purple";
					$color = "dark_purple";
					break;
				case TextFormat::GOLD:
					$pointer["color"] = "gold";
					$color = "gold";
					break;
				case TextFormat::GRAY:
					$pointer["color"] = "gray";
					$color = "gray";
					break;
				case TextFormat::DARK_GRAY:
					$pointer["color"] = "dark_gray";
					$color = "dark_gray";
					break;
				case TextFormat::BLUE:
					$pointer["color"] = "blue";
					$color = "blue";
					break;
				case TextFormat::GREEN:
					$pointer["color"] = "green";
					$color = "green";
					break;
				case TextFormat::AQUA:
					$pointer["color"] = "aqua";
					$color = "aqua";
					break;
				case TextFormat::RED:
					$pointer["color"] = "red";
					$color = "red";
					break;
				case TextFormat::LIGHT_PURPLE:
					$pointer["color"] = "light_purple";
					$color = "light_purple";
					break;
				case TextFormat::YELLOW:
					$pointer["color"] = "yellow";
					$color = "yellow";
					break;
				case TextFormat::WHITE:
					$pointer["color"] = "white";
					$color = "white";
					break;
				default:
					$pointer["text"] = $token;
					break;
			}
		}

		if (isset($newString["extra"])) {
			foreach ($newString["extra"] as $k => $d) {
				if (!isset($d["text"])) {
					unset($newString["extra"][$k]);
				}
			}
		}

		$result = json_encode($newString, JSON_UNESCAPED_SLASHES);
		if ($result === false) {
			throw new InvalidArgumentException("Failed to encode result JSON: " . json_last_error_msg());
		}
		return $result;
	}

	/**
	 * @return string motd
	 */
	public function getMotd(): string
	{
		return (string)$this->getConfig()->get("motd", $this->getServer()->getMotd());
	}

	/**
	 * @return bool
	 */
	public function isOnlineMode(): bool
	{
		return $this->onlineMode;
	}

	/**
	 * @return string
	 */
	public function getDesktopPrefix(): string
	{
		return $this->desktopPrefix;
	}

	/**
	 * @return string ASN1 Public Key
	 */
	public function getASN1PublicKey(): string
	{
		$key = explode("\n", $this->publicKey);
		array_pop($key);
		array_shift($key);
		return base64_decode(implode(array_map("trim", $key)));
	}

	/**
	 * @param string $cipher cipher text
	 * @return string plain text
	 */
	public function decryptBinary(string $cipher): string
	{
		if ($this->rsaKey === null) {
			return "";
		}
		return $this->rsaKey->decrypt($cipher);
	}

	/**
	 * @param string $username
	 * @param int $timeout
	 * @return array|null
	 */
	public function getProfileCache(string $username, int $timeout = 60): ?array
	{
		if (isset($this->profileCache[$username]) && (microtime(true) - $this->profileCache[$username]["timestamp"] < $timeout)) {
			return $this->profileCache[$username]["profile"];
		} else {
			unset($this->profileCache[$username]);
			return null;
		}
	}

	/**
	 * @param string $username
	 * @param array $profile
	 */
	public function setProfileCache(string $username, array $profile): void
	{
		$this->profileCache[$username] = [
			"timestamp" => microtime(true),
			"profile" => $profile
		];
	}

	/**
	 * Return string of Compound Tag
	 * @return string
	 */
	public function getDimensionCodec(): string
	{
		return $this->dimensionCodec;
	}

	/**
	 * Return string of Compound Tag
	 * @return string
	 */
	public function getDimension(): string
	{
		return $this->dimension;
	}

	/**
	 * @param PlayerRespawnEvent $event
	 *
	 * @priority NORMAL
	 */
	public function onRespawn(PlayerRespawnEvent $event): void
	{
		$player = $event->getPlayer();
		// TODO: Handle desktop player respawn
	}

	/**
	 * @param BlockPlaceEvent $event
	 *
	 * @priority NORMAL
	 */
	public function onPlace(BlockPlaceEvent $event): void
	{
		$player = $event->getPlayer();
		$block = $event->getBlockAgainst();
		// TODO: Handle sign placement for desktop players
	}

	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority NORMAL
	 */
	public function onBreak(BlockBreakEvent $event): void
	{
		$player = $event->getPlayer();
		// TODO: Handle desktop player block breaking
	}

	/**
	 * @param PlayerMoveEvent $event
	 *
	 * @priority NORMAL
	 */
	public function onPlayerMove(PlayerMoveEvent $event): void
	{
		$player = $event->getPlayer();
		// TODO: Handle desktop player movement
	}

	/**
	 * Handle Bedrock player chat and send to Java players
	 *
	 * @param PlayerChatEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerChat(PlayerChatEvent $event): void
	{
		if ($event->isCancelled()) {
			return;
		}

		$player = $event->getPlayer();
		$message = "<" . $player->getName() . "> " . $event->getMessage();

		// Send to all Java players
		if ($this->interface !== null) {
			$this->interface->broadcastChatToJava($message);
		}
	}

	/**
	 * Handle Bedrock player join and notify Java players
	 *
	 * @param PlayerJoinEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerJoin(PlayerJoinEvent $event): void
	{
		$player = $event->getPlayer();
		$message = "§e" . $player->getName() . " joined the game";

		if ($this->interface !== null) {
			$this->interface->broadcastChatToJava($message);
		}
	}

	/**
	 * Handle Bedrock player quit and notify Java players
	 *
	 * @param PlayerQuitEvent $event
	 * @priority MONITOR
	 */
	public function onPlayerQuit(PlayerQuitEvent $event): void
	{
		$player = $event->getPlayer();
		$message = "§e" . $player->getName() . " left the game";

		if ($this->interface !== null) {
			$this->interface->broadcastChatToJava($message);
		}
	}

	/**
	 * Broadcast a message from Java player to all Bedrock players
	 *
	 * @param string $username
	 * @param string $message
	 */
	public function broadcastJavaChat(string $username, string $message): void
	{
		$this->getServer()->broadcastMessage("<" . $username . "> " . $message);
	}

	/**
	 * Get ProtocolInterface
	 * @return ProtocolInterface|null
	 */
	public function getInterface(): ?ProtocolInterface
	{
		return $this->interface;
	}

	protected function onEnable(): void
	{
		$this->saveDefaultConfig();
		$this->saveResource("server-icon.png", false);
		$this->reloadConfig();

		ConvertUtils::init();

		// Check if resources exist
		if (!file_exists($this->getDataFolder() . "dimensionCodec.dat")) {
			$this->saveResource("dimensionCodec.dat", true);
		}
		if (!file_exists($this->getDataFolder() . "dimension.dat")) {
			$this->saveResource("dimension.dat", true);
		}
		if (!file_exists($this->getDataFolder() . "blockStateMapping.json")) {
			$this->saveResource("blockStateMapping.json", true);
		}

		$this->dimensionCodec = file_get_contents($this->getDataFolder() . "dimensionCodec.dat") ?: "";
		$this->dimension = file_get_contents($this->getDataFolder() . "dimension.dat") ?: "";

		$this->getLogger()->info("OS: " . php_uname());
		$this->getLogger()->info("PHP version: " . PHP_VERSION);
		$this->getLogger()->info("PMMP Server version: " . $this->getServer()->getVersion());
		$this->getLogger()->info("PMMP API version: " . $this->getServer()->getApiVersion());

		if (!$this->setupComposer()) {
			$this->getLogger()->critical("Composer autoloader not found. Run 'composer install' in the plugin directory.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		if (!$this->getConfig()->exists("motd")) {
			$this->getLogger()->warning("No motd has been set. The server description will be empty.");
		}

		$this->onlineMode = (bool)$this->getConfig()->get("online-mode", false);
		if ($this->onlineMode) {
			$this->getLogger()->info("Online mode is enabled - generating RSA keypair...");
			$this->setupEncryption();
		}

		$this->desktopPrefix = (string)$this->getConfig()->get("desktop-prefix", "PC_");

		$this->getLogger()->info("Starting Minecraft: Java Edition server on " .
			($this->getIp() === "0.0.0.0" ? "*" : $this->getIp()) . ":" . $this->getPort() .
			" version " . ServerThread::VERSION);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->translator = new Translator();
		$this->interface = new ProtocolInterface(
			$this,
			$this->getServer(),
			$this->translator,
			(int)$this->getConfig()->get("network-compression-threshold", 256)
		);

		$this->getServer()->getNetwork()->registerInterface($this->interface);
	}

	private function setupComposer(): bool
	{
		$autoload = $this->getFile() . 'vendor/autoload.php';

		if (is_file($autoload)) {
			$this->getLogger()->info("Registering Composer autoloader...");
			require_once $autoload;
			return true;
		}

		// Try to find autoload in library folder
		$libraryAutoload = $this->getFile() . 'library/autoload.php';
		if (is_file($libraryAutoload)) {
			$this->getLogger()->info("Registering library autoloader...");
			require_once $libraryAutoload;
			return true;
		}

		// No autoloader found, but we can still work without phpseclib (offline mode only)
		$this->getLogger()->warning("No Composer autoloader found. Online mode will be disabled.");
		$this->getLogger()->warning("To enable online mode, run 'composer install' in the BigBrother plugin directory.");
		return true; // Return true so plugin still loads for offline mode
	}

	private function setupEncryption(): void
	{
		try {
			if (!class_exists('\phpseclib3\Crypt\RSA')) {
				$this->getLogger()->warning("phpseclib3 not found, online mode disabled");
				$this->onlineMode = false;
				return;
			}
			$rsa = RSA::createKey(1024);
			$this->rsaKey = $rsa;
			$this->privateKey = $rsa->toString('PKCS1');
			$this->publicKey = $rsa->getPublicKey()->toString('PKCS8');
			$this->getLogger()->info("RSA keypair generated successfully");
		} catch (Throwable $e) {
			$this->getLogger()->error("Failed to generate RSA keypair: " . $e->getMessage());
			$this->onlineMode = false;
		}
	}

	/**
	 * @return string ip address
	 */
	public function getIp(): string
	{
		return (string)$this->getConfig()->get("interface", "0.0.0.0");
	}

	/**
	 * @return int port
	 */
	public function getPort(): int
	{
		return (int)$this->getConfig()->get("port", 25565);
	}

	protected function onDisable(): void
	{
		if ($this->interface !== null) {
			$this->interface->shutdown();
		}
	}
}

