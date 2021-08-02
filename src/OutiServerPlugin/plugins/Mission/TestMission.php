<?php

declare(strict_types=1);

namespace OutiServerPlugin\Misson;

use pjz9n\mission\libs\dktapps\pmforms\CustomFormResponse;
use pjz9n\mission\libs\dktapps\pmforms\element\CustomFormElement;
use pjz9n\mission\mission\executor\Executor;
use pjz9n\mission\mission\Mission;
use pjz9n\mission\util\FormResponseProcessFailedException;

class TestMission extends Executor
{

    public static function getType(): string
    {
        // TODO: Implement getType() method.
    }

    public static function getCreateFormElements(): array
    {
        // TODO: Implement getCreateFormElements() method.
    }

    public static function createByFormResponse(CustomFormResponse $response, Mission $parentMission)
    {
        // TODO: Implement createByFormResponse() method.
    }

    public function getSettingFormElements(): array
    {
        // TODO: Implement getSettingFormElements() method.
    }

    public function processSettingFormResponse(CustomFormResponse $response): void
    {
        // TODO: Implement processSettingFormResponse() method.
    }
}