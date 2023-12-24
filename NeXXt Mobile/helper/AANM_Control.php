<?php

/**
 * @project       Alarmanruf/NeXXt Mobile/helper/
 * @file          AANM_Control.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait AANM_Control
{
    /**
     * Gets the current balance from NeXXt Mobile.
     *
     * @return void
     * @throws Exception
     */
    public function GetCurrentBalance(): void
    {
        $this->SetTimerInterval('GetCurrentBalance', 0);
        if ($this->CheckMaintenance()) {
            return;
        }
        $token = $this->ReadPropertyString('Token');
        if (empty($token)) {
            return;
        } else {
            $token = rawurlencode($token);
        }
        $timeout = $this->ReadPropertyInteger('Timeout');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.nexxtmobile.de/?mode=user&token=' . $token . '&function=getBalance',
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => 60]);
        $result = curl_exec($ch);
        if (!curl_errno($ch)) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            switch ($httpCode) {
                case $httpCode >= 200 && $httpCode < 300:
                    $this->SendDebug(__FUNCTION__, 'Response: ' . $result, 0);
                    $data = json_decode($result, true);
                    if (!empty($data)) {
                        if (array_key_exists('isError', $data)) {
                            $isError = $data['isError'];
                            if ($isError) {
                                $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten!', 0);
                            }
                        } else {
                            $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten!', 0);
                        }
                        if (array_key_exists('result', $data)) {
                            if (array_key_exists('balanceFormated', $data['result'])) {
                                $balance = $data['result']['balanceFormated'] . ' €';
                                $this->SendDebug(__FUNCTION__, 'Aktuelles Guthaben: ' . $balance, 0);
                                $this->SetValue('CurrentBalance', $balance);
                            }
                        }
                    } else {
                        $this->SendDebug(__FUNCTION__, 'Keine Rückantwort erhalten!', 0);
                    }
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            }
        } else {
            $error_msg = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten: ' . json_encode($error_msg), 0);
        }
    }

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
        $location = $this->ReadPropertyString('Location');
        //Deactivate
        if (!$State) {
            $this->SendDebug(__FUNCTION__, 'Der Alarmanruf wird beendet', 0);
            $this->WriteAttributeString('Announcement', '');
            $this->SetValue('AlarmCall', false);
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
        }
        //Activate
        if ($State) {
            $this->WriteAttributeString('Announcement', $Announcement);
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
        $location = $this->ReadPropertyString('Location');
        $timestamp = date('d.m.Y, H:i:s');
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
            if ($location == '') {
                $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
            } else {
                $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
            }
            $this->UpdateAlarmProtocol($logText, 0);
            $this->ToggleAlarmCall(false, '');
            //Get current balance
            $this->SetTimerInterval('GetCurrentBalance', 30 * 1000);
        } else {
            //Revert on failure
            $this->SetValue('AlarmCall', false);
            $text = 'Fehler, der Alarmanruf konnte nicht ausgelöst werden!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
            //Protocol
            if ($location == '') {
                $logText = $timestamp . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
            } else {
                $logText = $timestamp . ', ' . $this->ReadPropertyString('Location') . ', Alarmanruf, ' . $text . ' (ID ' . $this->InstanceID . ')';
            }
            $this->UpdateAlarmProtocol($logText, 0);
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
        $token = $this->ReadPropertyString('Token');
        if (empty($token)) {
            return false;
        } else {
            $token = rawurlencode($token);
        }
        $originator = $this->ReadPropertyString('SenderPhoneNumber');
        if (empty($originator) || strlen($originator) <= 3) {
            return false;
        } else {
            $originator = rawurlencode($originator);
        }
        //Enter semaphore
        if (!$this->LockSemaphore('ExecuteAlarmCall')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, das Semaphore wurde erreicht!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', das Semaphore wurde erreicht!', KL_WARNING);
            $this->UnlockSemaphore('ExecuteAlarmCall');
            return false;
        }
        //Send data to NeXXt Mobile
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Rufnummer: ' . $PhoneNumber, 0);
        $this->SendDebug(__FUNCTION__, 'Ansagetext: ' . $Announcement, 0);
        $this->SendDebug(__FUNCTION__, 'Der Teilnehmer wird angerufen', 0);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.nexxtmobile.de/?mode=user&token=' . $token . '&function=callTTS&originator=' . $originator . '&number=' . rawurlencode($PhoneNumber) . '&text=' . rawurlencode($Announcement) . '&phase=execute&language=de',
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_CONNECTTIMEOUT => $this->ReadPropertyInteger('Timeout'),
            CURLOPT_TIMEOUT        => 60]);
        $response = curl_exec($ch);
        if (!curl_errno($ch)) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            if ($httpCode == ($httpCode >= 200 && $httpCode < 300)) {
                $this->SendDebug(__FUNCTION__, 'Response: ' . $response, 0);
                $data = json_decode($response, true);
                if (!empty($data)) {
                    if (array_key_exists('isError', $data)) {
                        $isError = $data['isError'];
                        if (!$isError) {
                            $result = true;
                        }
                    }
                    if (array_key_exists('result', $data)) {
                        if (array_key_exists('balanceFormated', $data['result'])) {
                            $balance = $data['result']['balanceFormated'] . ' €';
                            $this->SendDebug(__FUNCTION__, 'Aktuelles Guthaben: ' . $balance, 0);
                            $this->SetValue('CurrentBalance', $balance);
                        }
                    }
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, json_encode(curl_error($ch)), 0);
        }
        //Leave semaphore
        $this->UnlockSemaphore('ExecuteAlarmCall');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Der Teilnehmer wurde angerufen', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'Fehler, der Teilnehmer konnte nicht angerufen werden!', 0);
        }
        return $result;
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