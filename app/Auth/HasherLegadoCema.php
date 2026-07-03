<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Auth;

use Illuminate\Hashing\BcryptHasher;

class HasherLegadoCema extends BcryptHasher
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        if ($hashedValue === null || $hashedValue === '') {
            return false;
        }
        if (str_starts_with($hashedValue, '$wp')) {
            $pre = base64_encode(hash_hmac('sha384', trim((string) $value), 'wp-sha384', true));

            return password_verify($pre, substr($hashedValue, 3));
        }
        if (str_starts_with($hashedValue, '$P$') || str_starts_with($hashedValue, '$H$')) {
            return hash_equals($hashedValue, $this->phpass(trim((string) $value), $hashedValue));
        }

        return parent::check($value, $hashedValue, $options);
    }

    public function needsRehash($hashedValue, array $options = []): bool
    {
        $h = (string) $hashedValue;
        if (str_starts_with($h, '$wp') || str_starts_with($h, '$P$') || str_starts_with($h, '$H$')) {
            return true;
        }

        return parent::needsRehash($hashedValue, $options);
    }

    public function phpass(#[\SensitiveParameter] string $password, string $setting): string
    {
        $output = '*0';
        if (substr($setting, 0, 2) === $output) {
            $output = '*1';
        }
        if (substr($setting, 0, 3) !== '$P$' && substr($setting, 0, 3) !== '$H$') {
            return $output;
        }
        $countLog2 = strpos(self::ITOA64, $setting[3]);
        if ($countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }
        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $output;
        }
        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).$this->encode64($hash, 16);
    }

    private function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3F];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3F];
        } while ($i < $count);

        return $output;
    }
}
