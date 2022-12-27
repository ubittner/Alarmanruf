<?php

/**
 * @project       AlarmanrufVoIP
 * @file          AAVOIP_Control.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait AAVOIP_Control
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
    public function ToggleAlarmCall(bool $State, string $Announcement): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $statusText = 'Aus';
        if ($State) {
            $statusText = 'An';
        }
        $this->SendDebug(__FUNCTION__, 'Status: ' . $statusText, 0);
        $this->SetTimerInterval('ActivateAlarmCall', 0);
        if ($State) {
            if ($this->CheckMaintenance()) {
                return false;
            }
        }
        $result = true;
        $timestamp = date('d.m.Y, H:i:s');
        $actualAlarmCallState = $this->GetValue('AlarmCall');
        //Deactivate
        if (!$State) {
            $this->SendDebug(__FUNCTION__, 'Der Alarmanruf wird beendet', 0);
            $this->WriteAttributeString('Announcement', '');
            $this->SetValue('AlarmCall', false);
            //Protocol
            if ($actualAlarmCallState) {
                $this->UpdateAlarmProtocol($timestamp . ', Der Alarmanruf wurde beendet' . '. (ID ' . $this->InstanceID . ')', 0);
            }
        }
        //Activate
        if ($State) {
            $this->WriteAttributeString('Announcement', $Announcement);
            //Delay
            $delay = $this->ReadPropertyInteger('AlarmCallDelay');
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
                    $this->UpdateAlarmProtocol($timestamp . ', ' . $text . '. (ID ' . $this->InstanceID . ')', 0);
                }
            } //No delay, activate alarm call immediately
            else {
                if (!$actualAlarmCallState) {
                    $result = $this->ActivateAlarmCall();
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
        $this->SetValue('AlarmCall', true);
        $result = false;
        $announcement = $this->ReadAttributeString('Announcement');
        if (empty($announcement)) {
            $announcement = $this->ReadPropertyString('DefaultAnnouncement');
        }
        $recipients = json_decode($this->ReadPropertyString('Recipients'), true);
        foreach ($recipients as $recipient) {
            $phoneNumber = (string) $recipient['PhoneNumber'];
            if ($recipient['Use'] && strlen($phoneNumber) > 3) {
                $this->SendDebug(__FUNCTION__, 'Name: ' . $recipient['Name'], 0);
                $response = $this->ExecuteAlarmCall($phoneNumber, $announcement);
                if ($response) {
                    $result = true;
                }
            }
        }
        $this->WriteAttributeString('Announcement', '');
        if ($result) {
            $text = 'Der Alarmanruf wurde erfolgreich ausgelöst';
            $this->SendDebug(__FUNCTION__, $text, 0);
            //Protocol
            $this->UpdateAlarmProtocol($text . '. (ID ' . $this->InstanceID . ')', 0);
            $this->ToggleAlarmCall(false, '');
        } else {
            //Revert on failure
            $this->SetValue('AlarmCall', false);
            $text = 'Fehler, der Alarmanruf konnte nicht ausgelöst werden!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
            //Protocol
            $this->UpdateAlarmProtocol($text . ' (ID ' . $this->InstanceID . ')', 0);
        }
        return $result;
    }

    /**
     * Starts the automatic deactivation.
     */
    public function StartAutomaticDeactivation(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetValue('Active', false);
        //Turn the alarm call off
        $this->ToggleAlarmCall(false, '');
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
     * @param string $PhoneNumber
     * @param string $Announcement
     *
     * @return bool
     * false =  an error occurred
     * true =   successful
     *
     * @throws Exception
     */
    private function ExecuteAlarmCall(string $PhoneNumber, string $Announcement): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SetTimerInterval('ActivateAlarmCall', 0);
        if ($this->CheckMaintenance()) {
            return false;
        }
        //Enter semaphore
        if (!$this->LockSemaphore('ExecuteAlarmCall')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore wurde erreicht!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore wurde erreicht!', KL_WARNING);
            $this->UnlockSemaphore('ExecuteAlarmCall');
            return false;
        }
        //Call recipient
        $voipID = $this->ReadPropertyInteger('VoIP');
        if ($voipID <= 1 || @!IPS_ObjectExists($voipID)) { //0 = main category, 1 = none
            return false;
        }
        $pollyID = $this->ReadPropertyInteger('TTSAWSPolly');
        $duration = $this->ReadPropertyInteger('VoIPDuration');
        $scriptText = '
        $id = VoIP_Connect(' . $voipID . ', "' . $PhoneNumber . '");
        for($i = 0; $i < ' . $duration . '; $i++) {
            IPS_Sleep(1000);
            $c = VoIP_GetConnection(' . $voipID . ', $id);
            if($c["Connected"]) {
                break;
            }
        }
        VoIP_Disconnect(' . $voipID . ', $id);';
        if ($pollyID > 1 && @IPS_ObjectExists($pollyID)) { //0 = main category, 1 = none
            $scriptText = '
            $id = VoIP_Connect(' . $voipID . ', "' . $PhoneNumber . '");
            for($i = 0; $i < ' . $duration . '; $i++) {
                IPS_Sleep(1000);
                $c = VoIP_GetConnection(' . $voipID . ', $id);
                if($c["Connected"]) {
                    if (' . $pollyID . ' != 0 && @IPS_ObjectExists(' . $pollyID . ')) {
                        VoIP_PlayWave(' . $voipID . ', $id, TTSAWSPOLLY_GenerateFile(' . $pollyID . ', "' . $Announcement . '"));
                        return;
                    }
                }
            }
            VoIP_Disconnect(' . $voipID . ', $id);';
        }
        IPS_RunScriptText($scriptText);
        //Leave semaphore
        $this->UnlockSemaphore('ExecuteAlarmCall');
        return true;
    }

    /**
     * Sets the timer for automatic deactivation.
     *
     * @return void
     * @throws Exception
     */
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
            $this->ToggleAlarmCall(false, '');
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