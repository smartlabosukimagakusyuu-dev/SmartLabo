<?php
declare(strict_types=1);

namespace SmartLabo\Intake;

/** 設定の不備。fail closed のために投げる（起動を続行させない） */
final class ConfigException extends \RuntimeException
{
}
