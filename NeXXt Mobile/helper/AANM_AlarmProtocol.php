<?php

/**
 * @project       AlarmanrufNeXXt Mobile
 * @file          AANM_AlarmProtcol.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AANM_AlarmProtocol
{
    #################### Private

    /**
     * @param string $LogText
     * @param int $LogType
     * 0 =  Event message
     * 1 =  State message
     * 2 =  Alarm message
     *
     * @return void
     * @throws Exception
     */
    private function UpdateAlarmProtocol(string $LogText, int $LogType): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($this->CheckMaintenance()) {
            return;
        }
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if ($id > 1 && @IPS_ObjectExists($id)) {
            @AP_UpdateMessages($id, $LogText, $LogType);
        }
    }
}