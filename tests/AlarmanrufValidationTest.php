<?php

declare(strict_types=1);

namespace tests;

use TestCaseSymconValidation;

include_once __DIR__ . '/stubs/Validator.php';

class AlarmanrufValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Alarmanruf(): void
    {
        $this->validateModule(__DIR__ . '/../Alarmanruf');
    }

    public function testValidateModule_NeXXtMobile(): void
    {
        $this->validateModule(__DIR__ . '/../NeXXt Mobile');
    }

    public function testValidateModule_VoIP(): void
    {
        $this->validateModule(__DIR__ . '/../VoIP');
    }
}