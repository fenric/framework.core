<?php
/**
 * It's free open-source software released under the MIT License.
 *
 * @author Anatoly Fenric <anatoly.fenric@gmail.com>
 * @copyright Copyright (c) 2013-2018 by Fenric Laboratory
 * @license https://github.com/fenric/framework/blob/master/LICENSE.md
 * @link https://github.com/fenric/framework
 */

namespace Fenric;

/**
 * Console
 */
class Console extends Collection
{

	/**
	 * Output styles
	 */
	public const STYLE_BOLD          = ['1', '22'];
	public const STYLE_UNDERLINE     = ['4', '24'];
	public const STYLE_BLINK         = ['5', '25'];
	public const STYLE_REVERSE       = ['7', '27'];
	public const STYLE_HIDDEN        = ['8', '28'];

	/**
	 * Foreground colors
	 */
	public const FOREGROUND_BLACK   = ['30', '39'];
	public const FOREGROUND_RED     = ['31', '39'];
	public const FOREGROUND_GREEN   = ['32', '39'];
	public const FOREGROUND_YELLOW  = ['33', '39'];
	public const FOREGROUND_BLUE    = ['34', '39'];
	public const FOREGROUND_PURPLE  = ['35', '39'];
	public const FOREGROUND_CYAN    = ['36', '39'];
	public const FOREGROUND_WHITE   = ['37', '39'];
	public const FOREGROUND_DEFAULT = ['39', '39'];
	public const FOREGROUND_RESET   = ['39', '39'];

	/**
	 * Background colors
	 */
	public const BACKGROUND_BLACK   = ['40', '49'];
	public const BACKGROUND_RED     = ['41', '49'];
	public const BACKGROUND_GREEN   = ['42', '49'];
	public const BACKGROUND_YELLOW  = ['43', '49'];
	public const BACKGROUND_BLUE    = ['44', '49'];
	public const BACKGROUND_PURPLE  = ['45', '49'];
	public const BACKGROUND_CYAN    = ['46', '49'];
	public const BACKGROUND_WHITE   = ['47', '49'];
	public const BACKGROUND_DEFAULT = ['49', '49'];
	public const BACKGROUND_RESET   = ['49', '49'];

	/**
	 * Size of the screen
	 */
	public $width;
	public $height;

	/**
	 * Constructor of the class
	 */
	public function __construct(array $tokens)
	{
		// Contains the file name
		unset($tokens[0]);

		parent::__construct(
			$this->parse($tokens)
		);

		$this->width = exec('tput cols');
		$this->height = exec('tput lines');
	}

	/**
	 * Outputs info
	 */
	public function info(string $string)
	{
		return $this->line($this->style($string, [
			self::FOREGROUND_GREEN,
		]));
	}

	/**
	 * Outputs comment
	 */
	public function comment(string $string)
	{
		return $this->line($this->style($string, [
			self::FOREGROUND_YELLOW,
		]));
	}

	/**
	 * Outputs success
	 */
	public function success(string $string)
	{
		return $this->block($string, [
			self::FOREGROUND_BLACK,
			self::BACKGROUND_GREEN,
		]);
	}

	/**
	 * Outputs warning
	 */
	public function warning(string $string)
	{
		return $this->block($string, [
			self::FOREGROUND_BLACK,
			self::BACKGROUND_YELLOW,
		]);
	}

	/**
	 * Outputs error
	 */
	public function error(string $string)
	{
		return $this->block($string, [
			self::FOREGROUND_WHITE,
			self::BACKGROUND_RED,
		]);
	}

	/**
	 * Outputs progress
	 */
	public function progress(int $max, callable $callback)
	{
		$time = microtime(true);
		$memory = memory_get_peak_usage(true);

		$render = function($step, $max) use($time, $memory)
		{
			$progress = $step / $max;
			$percent = round($progress * 100);

			$time = round(microtime(true) - $time);
			$memory = round(memory_get_peak_usage(true) - $memory);

			$remaining = round(($time / ($step ?: 1)) * ($max - $step));
			$estimated = round(($time / ($step ?: 1)) * ($max));

			$width = 20;
			$filled = floor($width * $progress);
			$unfilled = $width - $filled;

			$context[':bar'] = str_repeat('▓', $filled);
			$context[':bar'] .= str_repeat('░', $unfilled);

			$context[':step'] = $step;
			$context[':max'] = $max;
			$context[':percent'] = $percent;

			$context[':time'] = $time;
			$context[':remaining'] = $remaining;
			$context[':estimated'] = $estimated;

			$context[':memory'] = round($memory / (1024 ** 2));
			$context[':limit'] = ini_get('memory_limit');

			$template = ':step / :max   :percent% :bar   :time sec. (≈ :estimated sec.)   :memory MiB (:limit)';

			$this->stdout("\015\033\1332K" . strtr($template, $context));
		};

		$render(0, $max);

		for ($step = 1; $step <= $max; $step++)
		{
			if ($callback() === null)
			{
				$render($step, $max);

				continue;
			}

			$render($max, $max);
		}

		$this->eol(1);
	}

	/**
	 * Outputs block
	 */
	public function block(string $string, array $styles) : string
	{
		$width = 120;
		$padding = 3;

		if ($width > $this->width) {
			$width = $this->width;
		}

		$lines = explode(PHP_EOL, wordwrap(PHP_EOL . trim($string) . PHP_EOL, $width - ($padding * 2), PHP_EOL, true));

		foreach ($lines as & $line)
		{
			$line = $this->style(str_repeat(' ', $padding) . ltrim($line) . str_repeat(' ', $padding) . str_repeat(' ', $width - ($padding * 2) - $this->length(trim($line))), $styles);
		}

		return $this->line(implode(PHP_EOL, $lines));
	}

	/**
	 * Outputs line
	 */
	public function line(string $string)
	{
		return $this->stdout($string . PHP_EOL);
	}

	/**
	 * Outputs end of line
	 */
	public function eol(int $count)
	{
		return $this->stdout(
			str_repeat(PHP_EOL, $count)
		);
	}

	/**
	 * Reads the stdin stream
	 */
	public function stdin()
	{
		return fgets(STDIN);
	}

	/**
	 * Writes to the stdout stream
	 */
	public function stdout(string $string)
	{
		return fwrite(STDOUT, $string);
	}

	/**
	 * Writes to the stderr stream
	 */
	public function stderr(string $string)
	{
		return fwrite(STDERR, $string);
	}

	/**
	 * Stylizes a string
	 */
	public function style(string $string, array $styles) : string
	{
		$open = $close = [];

		foreach ($styles as $style)
		{
			list($open[], $close[]) = $style;
		}

		$format = "\033\133%sm%s\033\133%sm";

		return sprintf($format, join(';', $open), $string, join(';', $close));
	}

	/**
	 * Unstylizes a string
	 */
	public function unstyle(string $string) : string
	{
		$regexp = "/\033\133[\d;]*\w/";

		return preg_replace($regexp, '', $string);
	}

	/**
	 * Length of string
	 */
	public function length(string $string) : int
	{
		return mb_strlen(
			$this->unstyle($string)
		);
	}

	/**
	 * Parse a tokens
	 */
	public function parse(array $tokens) : array
	{
		$result = [];
		$lastOption = '';

		foreach ($tokens as $token)
		{
			// Short option
			if (strlen($token) > 1)
			{
				if (strcmp($token[0], '-') === 0)
				{
					if (strcmp($token[1], '-') !== 0)
					{
						$options = substr($token, 1);
						$lastOption = substr($token, -1);
						$equalpos = strpos($token, '=');
						$value = true;

						if ($equalpos !== false)
						{
							$options = substr($token, 1, $equalpos - 1);
							$lastOption = '';
							$value = substr($token, $equalpos + 1);
						}

						$length = strlen($options);

						for ($i = 0; $i < $length; $i++)
						{
							$result[$options[$i]] = $value;
						}

						continue;
					}
				}
			}

			// Long option
			if (strlen($token) > 2)
			{
				if (strcmp($token[0], '-') === 0)
				{
					if (strcmp($token[1], '-') === 0)
					{
						$option = substr($token, 2);
						$lastOption = substr($token, 2);
						$equalpos = strpos($token, '=');
						$value = true;

						if ($equalpos !== false)
						{
							$option = substr($token, 2, $equalpos - 2);
							$lastOption = '';
							$value = substr($token, $equalpos + 1);
						}

						$result[$option] = $value;

						continue;
					}
				}
			}

			// Last option value
			if (strlen($lastOption) > 0)
			{
				$result[$lastOption] = $token;

				$lastOption = '';

				continue;
			}

			// Other arguments
			$result[] = $token;
		}

		return $result;
	}
}
