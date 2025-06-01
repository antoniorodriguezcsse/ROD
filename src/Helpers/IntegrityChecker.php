<?php
namespace Fallen\SecondLife\Helpers;

class IntegrityChecker
{

  public static function llXorBase64(string $s1, string $s2): string
  {
    $s1 = base64_decode($s1); $l1 = strlen($s1);
    $s2 = base64_decode($s2);

    if ($l1 > strlen($s2)) $s2 = str_pad($s2, $l1, $s2, STR_PAD_RIGHT);
    return base64_encode($s1 ^ $s2);

  }

  public static function create_checksum(string $secret, string $input): string
  {
    $seed = base64_encode($secret);
    $payload = base64_encode(implode('~', [$input, $secret, $seed]));
    return IntegrityChecker::llXorBase64($payload, $seed);
  }

  public static function verify_checksum(string $secret, string $checksum, string $input): int
  {
    $to_validate = IntegrityChecker::create_checksum($secret, $input);
    return strcmp($to_validate, $checksum) === 0;
  }
}