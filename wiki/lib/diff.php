<?php

namespace lib;

class DiffTuple {
	public $mode;
	public $lines = array();

	function __construct($mode, $lines) {
		$this->mode = $mode;
		$this->lines = $lines;
	}
}

class Diff {
	const EQUAL = 1;
	const ADD = 2;
	const REM = 3;

	protected function _addNumbers(&$item, $key) {
		$item = array($key + 1, $item);
	}

	protected function split($text) {
		$split = preg_split("/\r\n|\n/", $text);
		array_walk($split, array($this, "_addNumbers"));
		return $split;
	}

	function compute($text1, $text2) {
		// All new (text 1 empty)
		if (empty($text1)) {
			return array(new DiffTuple(Diff::ADD, $this->split($text2)));
		}

		// All removed (text 2 empty)
		if (empty($text2)) {
			return array(new DiffTuple(Diff::REM, $this->split($text1)));
		}

		// Speedup if texts are the same
		if ($text1 == $text2) {
			return array(new DiffTuple(Diff::EQUAL, $this->split($text1)));
		}

		$split1 = $this->split($text1);
		$split2 = $this->split($text2);

		/*$commonPrefix = $this->commonPrefix($split1, $split2);
		$commonSuffix = $this->commonSuffix($split1, $split2);

		$split1 = array_slice($split1, count($commonPrefix), -count($commonSuffix));
		$split2 = array_slice($split2, count($commonPrefix), -count($commonSuffix));*/

		$diff = $this->computeDiff($split1, 0, count($split1), $split2, 0, count($split2));
		$diff[] = array(count($split1), count($split2), 0);

		$diff = $this->prepareDiff(
			$diff,
			$split1,
			$split2
		);

		$out = array();
		if (count($commonPrefix) > 0) {
			$out += $commonPrefix;
		}

		$out += $diff;

		if (count($commonSuffix) > 0) {
			$out += $commonSuffix;
		}

		return $out;
	}

	protected function commonPrefix(&$text1, &$text2) {
		$len1 = count($text1);
		$len2 = count($text2);

		$prefix = array();

		for ($i = 0; $i < min($len1, $len2); ++$i) {
			if ($text1[$i][1] == $text2[$i][1]) {
				$prefix[] = $text1[$i];
			} else {
				break;
			}
		}

		return $prefix;
	}

	protected function commonSuffix(&$text1, &$text2) {
		$suffix = array();

		for ($i = count($len1) - 1, $j = count($len2) - 1; $i >= 0 && $j >= 0; --$i, --$j) {
			if ($text1[$i][1] == $text[$j][1]) {
				$suffix[] = $text1[$i];
			} else {
				break;
			}
		}

		return array_reverse($suffix);
	}

	protected function prepareDiff($diffs, &$split1, &$split2) {
		$ia = 0;
		$ib = 0;

		$result = array();

		foreach ($diffs as $diff) {
			list($sa, $sb, $n) = $diff;

			$lines = array_slice($split1, $ia, $sa);
			if (count($lines) > 0) {
				$result[] = new DiffTuple(Diff::REM, $lines);
			}

			$lines = array_slice($split2, $ib, $sb);
			if (count($lines) > 0) {
				$result[] = new DiffTuple(Diff::ADD, $lines);
			}

			$lines = array_slice($split1, $sa, $sa + $n);
			if (count($lines) > 0) {
				$result[] = new Difftuple(Diff::EQUAL, $lines);
			}

			$ia = $sa + $n;
			$ib = $sb + $n;
		}

		return $result;
	}

	protected function longestMatchingSlice(&$split1, $s1, $e1, &$split2, $s2, $e2) {
		$sa = 0;
		$sb = 0;
		$n = 0;

		$runs = array();
		for ($i = $s1; $i < $e1; ++$i) {
			$new_runs = array();
			for ($j = $s2; $j < $e2; ++$j) {
				if ($split1[$i][1] == $split2[$j][1]) {
					if (isset($runs[$j - 1])) {
						$k = $new_runs[$j] = $runs[$j-1] + 1;
					} else {
						$k = $new_runs[$j] = 1;
					}

					if ($k > $n) {
						$sa = $i - $k + 1;
						$sb = $j - $k + 1;
						$n = $k;
					}
				}
			}
			$runs = $new_runs;
		}

		return array($sa, $sb, $n);
	}

	protected function computeDiff(&$split1, $s1, $e1, &$split2, $s2, $e2) {
		list($sa, $sb, $n) = $this->longestMatchingSlice($split1, $s1, $e1, $split2, $s2, $e2);

		if ($n == 0) {
			return array();
		}

		return array_merge(
			$this->computeDiff($split1, $s1, $sa, $split2, $s2, $sb),
			array(array($sa, $sb, $n)),
			$this->computeDiff($split1, $sa + $n, $e1, $split2, $sb + $n, $e2)
		);
	}
}

