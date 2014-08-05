<?php
namespace PHPDaemon\Utils;
use PHPDaemon\FS\FileSystem;

/**
 * Class Crypt
 * @package PHPDaemon\Utils
 */
class Crypt {
	use \PHPDaemon\Traits\ClassWatchdog;
	/**
	 * Generate keccak hash for string with salt
	 * @param string $str
	 * @param string $salt
	 * @param boolean $plain = false
	 * @return string
	 */
	public static function hash($str, $salt = '', $plain = false) {
		$size = 512;
		$rounds = 1;
		if (strncmp($salt, '$', 1) === 0) {
			$e = explode('$', $salt, 3);
			$ee = explode('=', $e[1]);
			if (ctype_digit($ee[0])) {
				$size = (int) $e[1];
			}
			if (isset($ee[1]) && ctype_digit($e[1])) {
				$size = (int)$e[1];
			}
		}
		$hash = $str . $salt;
		if ($rounds < 1) {
			$rounds = 1;
		}
		elseif ($rounds > 128) {
			$rounds = 128;
		}
		for ($i = 0; $i < $rounds; ++$i) {
			$hash = keccak_hash($hash, $size);
		}
		if ($plain) {
			return $hash;
		}
		return base64_encode($hash);
	}

	public static function randomString($len = 64, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-.', $cb = null, $pri = 0, $hang = false) {
		if ($cb === null) {
			Daemon::log('[CODE WARN] \\PHPDaemon\\Utils\\Crypt::randomString: non-callback way is not secure.'
					.' Please rewrite your code with callback function in third argument' . PHP_EOL . Debug::backtrace());

			$r = '';
			$m = strlen($chars) - 1;
			for ($i = 0; $i < $len; ++$i) {
				$r .= $chars[mt_rand(0, $m)];
			}
			return $r;
		}
		$charsLen = strlen($chars);
		$mask = static::getMinimalBitMask($charsLen - 1);
		$iterLimit = max($len, $len * 64);
		static::randomInts(1 * $len, function($ints) use ($cb, $chars, $charsLen, $len, $mask, &$iterLimit) {
			if ($ints === false) {
				call_user_func($cb, false);
				return;
			}
			$r = '';
			for ($i = 0, $s = sizeof($ints); $i < $s; ++$i) {
				// This is wasteful, but RNGs are fast and doing otherwise adds complexity and bias.
				$c = $ints[$i++] & $mask;
				// Only use the random number if it is in range, otherwise try another (next iteration).
				if ($c < $charsLen) {
					$r .= static::stringIdx($chars, $c);
				}
				// Guarantee termination
				if (--$iterLimit <= 0) {
					return false;
				}
			}
			$d = $len - strlen($r);
			if ($d > 0) {
				static::randomString($d, $chars, function($r2) use ($r, $cb) {
					call_user_func($cb, $r . $r2);
				});
				return;
			}
			call_user_func($cb, $r);
		}, $pri, $hang);
	}

	// Returns the character at index $index in $string in constant time.
    public static function stringIdx($str, $idx) {
        // FIXME: Make the const-time hack below work for all integer sizes, or
        // check it properly.
        $l = strlen($str);
        if ($l > 65535 || $idx > $l) {
            return false;
        }
        $r = 0;
        for ($i = 0; $i < $l; ++$i) {
            $x = $i ^ $idx;
            $mask = (((($x | ($x >> 16)) & 0xFFFF) + 0xFFFF) >> 16) - 1;
            $r |= ord($str[$i]) & $mask;
        }
        return chr($r);
    }

	public static function randomBytes($len, $cb, $pri = 0, $hang = false) {
		FileSystem::open('/dev/' . ($hang ? '' : 'u') . 'random', 'r', function ($file) use ($len, $cb, $pri) {
			if (!$file) {
				call_user_func($cb, false);
			}
			$file->read($len, 0, function($file, $data) use ($cb) {
				call_user_func($cb, $data);
			}, $pri);
		}, null, $pri);
	}

	public static function randomInts($numInts, $cb, $pri, $hang = false) {
		static::randomBytes(PHP_INT_SIZE * $numInts, function($bytes) use ($cb, $numInts) {
			if ($bytes === false) {
				call_user_func($cb, false);
				return;
			}
			$ints = [];
			for ($i = 0; $i < $numInts; ++$i) {
				$thisInt = 0;
				for ($j = 0; $j < PHP_INT_SIZE; ++$j) {
					$thisInt = ($thisInt << 8) | (ord($bytes[$i * PHP_INT_SIZE + $j]) & 0xFF);
				}
				// Absolute value in two's compliment (with min int going to zero)
				$thisInt = $thisInt & PHP_INT_MAX;
				$ints[] = $thisInt;
			}
			call_user_func($cb, $ints);
		}, $pri, $hang);
	}

	
	/**
	 * Returns the smallest bit mask of all 1s such that ($toRepresent & mask) = $toRepresent.
	 * @param string $toRepresent must be an integer greater than or equal to 1.
	 * @return int
	 */
	protected static function getMinimalBitMask($toRepresent)
	{
		if($toRepresent < 1)
			return false;
		$mask = 0x1;
		while($mask < $toRepresent)
			$mask = ($mask << 1) | 1;
		return $mask;
	}


	/**
	 * Compare strings
	 * @param string $a
	 * @param string $b
	 * @return boolean Equal?
	 */
	public static function compareStrings($a, $b) {
		$al = strlen($a);
		$bl = strlen($b);
		if ($al !== $bl) {
			return false;
		}
		$d = 0;
		for ($i = 0; $i < $al; ++$i) {
			$d |= ord($a[$i]) ^ ord($b[$i]);
		}
		return $d === 0;
	}
}

