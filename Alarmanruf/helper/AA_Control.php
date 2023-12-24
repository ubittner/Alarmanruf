<?php

/**
 * @project       Alarmanruf/Alarmanruf/helper/
 * @file          AA_Control.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpVoidFunctionResultUsedInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait AA_Control
{
    /**
     * Toggles the alarm call off or on.
     *
     * @param bool $State
     * false =  off
     * true =   on
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    public function ToggleAlarmCall(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $statusText = 'Aus';
        $value = 'false';
        if ($State) {
            $statusText = 'An';
            $value = 'true';
        }
        $this->SendDebug(__FUNCTION__, 'Status: ' . $statusText, 0);
        if ($State) {
            if ($this->CheckMaintenance()) {
                return false;
            }
        }
        $result = false;
        $id = $this->ReadPropertyInteger('AlarmCall');
        if ($id > 1 && @IPS_ObjectExists($id)) {
            $result = true;
            $timestamp = date('d.m.Y, H:i:s');
            $actualAlarmCallState = $this->GetValue('AlarmCall');
            $location = $this->ReadPropertyString('Location');
            //Deactivate
            if (!$State) {
                $this->SendDebug(__FUNCTION__, 'Der Alarmanruf wird beendet', 0);
                $this->SetTimerInterval('ActivateAlarmCall', 0);
                $this->SetTimerInterval('DeactivateAlarmCall', 0);
                $this->SetValue('AlarmCall', false);
                $commandControl = $this->ReadPropertyInteger('CommandControl');
                if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) {
                    $commands = [];
                    $commands[] = '@RequestAction(' . $id . ', ' . $value . ');';
                    $this->SendDebug(__FUNCTION__, 'Befehl: ' . json_encode(json_encode($commands)), 0);
                    $scriptText = self::ABLAUFSTEUERUNG_MODULE_PREFIX . '_ExecuteCommands(' . $commandControl . ', ' . json_encode(json_encode($commands)) . ');';
                    $this->SendDebug(__FUNCTION__, 'Ablaufsteuerung: ' . $scriptText, 0);
                    $result = @IPS_RunScriptText($scriptText);
                } else {
                    IPS_Sleep($this->ReadPropertyInteger('AlarmCallSwitchingDelay'));
                    //Enter semaphore
                    if (!$this->LockSemaphore('ToggleAlarmCall')) {
                        $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore wurde erreicht!', 0);
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore wurde erreicht!', KL_WARNING);
                        $this->UnlockSemaphore('ToggleAlarmCall');
                        //Revert
                        $this->SetValue('AlarmCall', $actualAlarmCallState);
                        $text = 'Fehler, der Alarmanruf konnte nicht beendet werden!';
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                        //Protocol
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                        return false;
                    }
                    $response = @RequestAction($id, false);
                    if (!$response) {
                        IPS_Sleep(self::DELAY_MILLISECONDS);
                        $response = @RequestAction($id, false);
                        if (!$response) {
                            IPS_Sleep(self::DELAY_MILLISECONDS * 2);
                            $response = @RequestAction($id, false);
                            if (!$response) {
                                $result = false;
                            }
                        }
                    }
                    //Leave semaphore
                    $this->UnlockSemaphore('ToggleAlarmCall');
                }
                if ($result) {
                    //Protocol
                    if ($actualAlarmCallState) {
                        $text = 'Der Alarmanruf wurde beendet';
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                } else {
                    //Revert on failure
                    $this->SetValue('AlarmCall', $actualAlarmCallState);
                    //Log
                    $text = 'Fehler, der Alarmanruf konnte nicht beendet werden!';
                    $this->SendDebug(__FUNCTION__, $text, 0);
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                    //Protocol
                    if ($actualAlarmCallState) {
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                }
            }
            //Activate
            if ($State) {
                //Delay
                $delay = $this->ReadPropertyInteger('AlarmCallSwitchOnDelay');
                if ($delay > 0) {
                    $this->SetTimerInterval('ActivateAlarmCall', $delay * 1000);
                    $unit = 'Sekunden';
                    if ($delay == 1) {
                        $unit = 'Sekunde';
                    }
                    $this->SetValue('AlarmCall', true);
                    $text = 'Der Alarmanruf wird in ' . $delay . ' ' . $unit . ' ausgelöst';
                    $this->SendDebug(__FUNCTION__, $text, 0);
                    if (!$actualAlarmCallState) {
                        //Protocol
                        if ($location == '') {
                            $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                        } else {
                            $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                        }
                        $this->UpdateAlarmProtocol($logText, 0);
                    }
                } //No delay, activate alarm call immediately
                else {
                    if (!$actualAlarmCallState) {
                        $result = $this->ActivateAlarmCall();
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Activates the alarm call.
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    public function ActivateAlarmCall(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetTimerInterval('ActivateAlarmCall', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        return $this->ExecuteAlarmCall();
    }

    /**
     * Deactivates the alarm call.
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    public function DeactivateAlarmCall(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetTimerInterval('DeactivateAlarmCall', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        return $this->ToggleAlarmCall(false);
    }

    /**
     * Starts the automatic deactivation.
     */
    public function StartAutomaticDeactivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetValue('Active', false);
        //Turn the alarm call off
        $this->ToggleAlarmCall(false);
        $this->SetAutomaticDeactivationTimer();
    }

    /**
     * Stops the automatic deactivation.
     */
    public function StopAutomaticDeactivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetValue('Active', true);
        $this->SetAutomaticDeactivationTimer();
    }

    #################### Private

    /**
     * Executes the alarm call.
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    private function ExecuteAlarmCall(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetTimerInterval('ActivateAlarmCall', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        $result = false;
        $id = $this->ReadPropertyInteger('AlarmCall');
        $location = $this->ReadPropertyString('Location');
        if ($id > 1 && @IPS_ObjectExists($id)) {
            $result = true;
            $timestamp = date('d.m.Y, H:i:s');
            $actualAlarmCallState = $this->GetValue('AlarmCall');
            $this->SendDebug(__FUNCTION__, 'Der Alarmanruf wird gestartet', 0);
            $this->SetValue('AlarmCall', true);
            $commandControl = $this->ReadPropertyInteger('CommandControl');
            if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) {
                $commands = [];
                $commands[] = '@RequestAction(' . $id . ', ' . true . ');';
                $this->SendDebug(__FUNCTION__, 'Befehl: ' . json_encode(json_encode($commands)), 0);
                $scriptText = self::ABLAUFSTEUERUNG_MODULE_PREFIX . '_ExecuteCommands(' . $commandControl . ', ' . json_encode(json_encode($commands)) . ');';
                $this->SendDebug(__FUNCTION__, 'Ablaufsteuerung: ' . $scriptText, 0);
                $result = @IPS_RunScriptText($scriptText);
            } else {
                IPS_Sleep($this->ReadPropertyInteger('AlarmCallSwitchingDelay'));
                //Enter semaphore
                if (!$this->LockSemaphore('ExecuteAlarmCall')) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore wurde erreicht!', 0);
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore wurde erreicht!', KL_WARNING);
                    $this->UnlockSemaphore('ExecuteAlarmCall');
                    //Revert
                    $this->SetValue('AlarmCall', $actualAlarmCallState);
                    $text = 'Fehler, der Alarmanruf konnte nicht gestartet werden!';
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                    //Protocol
                    if ($location == '') {
                        $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $this->UpdateAlarmProtocol($logText, 0);
                    return false;
                }
                $response = @RequestAction($id, true);
                if (!$response) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $response = @RequestAction($id, true);
                    if (!$response) {
                        IPS_Sleep(self::DELAY_MILLISECONDS * 2);
                        $response = @RequestAction($id, true);
                        if (!$response) {
                            $result = false;
                        }
                    }
                }
                //Leave semaphore
                $this->UnlockSemaphore('ExecuteAlarmCall');
            }
            if ($result) {
                $duration = $this->ReadPropertyInteger('AlarmCallSwitchOnDuration'); //Impulse
                $this->SetTimerInterval('DeactivateAlarmCall', $duration * 1000);
                if ($duration > 0) {
                    $unit = 'Sekunden';
                    if ($duration == 1) {
                        $unit = 'Sekunde';
                    }
                    $this->SendDebug(__FUNCTION__, 'Einschaltdauer, der Alarmanruf wird in ' . $duration . ' ' . $unit . ' automatisch beendet', 0);
                }
                //Protocol
                if ($actualAlarmCallState) {
                    $text = 'Der Alarmanruf wurde gestartet';
                    if ($location == '') {
                        $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $this->UpdateAlarmProtocol($logText, 0);
                }
            } else {
                //Revert on failure
                $this->SetValue('AlarmCall', $actualAlarmCallState);
                //Log
                $text = 'Fehler, der Alarmanruf konnte nicht gestartet werden!';
                $this->SendDebug(__FUNCTION__, $text, 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                //Protocol
                if ($actualAlarmCallState) {
                    if ($location == '') {
                        $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $this->UpdateAlarmProtocol($logText, 0);
                }
            }
        }
        return $result;
    }

    private function SetAutomaticDeactivationTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $use = $this->ReadPropertyBoolean('UseAutomaticDeactivation');
        //Start
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('AutomaticDeactivationStartTime');
        }
        $this->SetTimerInterval('StartAutomaticDeactivation', $milliseconds);
        //End
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('AutomaticDeactivationEndTime');
        }
        $this->SetTimerInterval('StopAutomaticDeactivation', $milliseconds);
    }

    /**
     * Gets the interval for a timer.
     *
     * @param string $TimerName
     * @return int
     * @throws Exception
     */
    private function GetInterval(string $TimerName): int
    {
        $timer = json_decode($this->ReadPropertyString($TimerName));
        $now = time();
        $hour = $timer->hour;
        $minute = $timer->minute;
        $second = $timer->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    /**
     * Checks the status of the automatic deactivation timer.
     *
     * @return bool
     * @throws Exception
     */
    private function CheckAutomaticDeactivationTimer(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if (!$this->ReadPropertyBoolean('UseAutomaticDeactivation')) {
            return false;
        }
        $start = $this->GetTimerInterval('StartAutomaticDeactivation');
        $stop = $this->GetTimerInterval('StopAutomaticDeactivation');
        if ($start > $stop) {
            //Deactivation timer is active, must be toggled to inactive
            $this->SetValue('Active', false);
            //Turn alarm call off
            $this->ToggleAlarmCall(false);
            return true;
        } else {
            //Deactivation timer is inactive, must be toggled to active
            $this->SetValue('Active', true);
            return false;
        }
    }

    /**
     * Attempts to set the semaphore and repeats this up to multiple times.
     *
     * @param $Name
     * @return bool
     * @throws Exception
     */
    private function LockSemaphore($Name): bool
    {
        $attempts = 1000;
        for ($i = 0; $i < $attempts; $i++) {
            if (IPS_SemaphoreEnter(__CLASS__ . '.' . $this->InstanceID . '.' . $Name, 1)) {
                $this->SendDebug(__FUNCTION__, 'Semaphore ' . $Name . ' locked', 0);
                return true;
            } else {
                IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    /**
     * Leaves the semaphore.
     *
     * @param $Name
     * @return void
     */
    private function UnlockSemaphore($Name): void
    {
        @IPS_SemaphoreLeave(__CLASS__ . '.' . $this->InstanceID . '.' . $Name);
        $this->SendDebug(__FUNCTION__, 'Semaphore ' . $Name . ' unlocked', 0);
    }
}