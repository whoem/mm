<?php
/**
 * mm: the PHP media library
 *
 * Copyright (c) 2007 David Persson
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 */

namespace mm\Media\Info\Adapter;

use Exception;

/**
 * This adapter contains a collection of methods which have no
 * external dependencies for analyzing WAVE files. Some of these
 * methods can be very resource intensive and return tons of data
 * (especially `samples()`).
 */
class NewWave extends \mm\Media\Info\Adapter {

	protected $_object;

	protected $_methods = ['samples'];

	public function __construct($file) {
		$this->_object = $file;
	}

	public function all() {
		$result = [];

		foreach ($this->_methods as $method) {
			$result[$method] = $this->{"_{$method}"}();
		}
		return $result;
	}

	public function get($name) {
		if (in_array($name, $this->_methods)) {
			return $this->{"_{$name}"}();
		}
	}

	protected function _samples() {
		$data = [];
		$handle = fopen($this->_object, 'r');

		$header[] = fread($handle, 4); // 'RIFF'
		$header[] = bin2hex(fread ($handle, 4));
		$header[] = fread($handle, 4); // 'WAVE'

		if ($header[0] != 'RIFF' || $header[2] != 'WAVE') {
			return;
		}
		$header[] = fread($handle, 4); // fmt-header signature: 'fmt '
		$header[] = fread($handle, 4); // length of following fmt-header
		$header[] = fread($handle, 2); // file format of header
		$header['channels'] = $this->_read($handle, 2, 's'); // channels
		$header[] = fread($handle, 4); // samples per second: 44100
		$header[] = fread($handle, 4); // bytes per second
		$header['blockAlign'] = $this->_read($handle, 2, 's'); // block align
		$header['sampleSize'] = $this->_read($handle, 2, 's'); // bits per sample: 8, 16 or 24 !$peek

		$header[] = fread($handle, 4); // data-header signature: 'data'
		$header[] = fread($handle, 4); // length of data block

		$seek = $header['channels'] == 2 ? 40 : 80;

		while (!feof($handle)) {
			for ($i = 0; $i < $header['channels']; $i++) {
				$sample = $this->_read($handle, $header['blockAlign'], 's');

				if ($sample === false) {
					break(2);
				}
				$frame[$i] = $sample;
			}
			$data[] = $frame;

			fseek($handle, $seek, SEEK_CUR);
		}
		fclose($handle);

		if (!$data) {
			throw new Exception('No sample data.');
		}
		return $data;
	}

	protected function _read($handle, $bytes, $format) {
		$result = fread($handle, $bytes);

		if (!$result) {
			return false;
		}
		$result = unpack($format, $result);
		return current($result);
	}
}

?>