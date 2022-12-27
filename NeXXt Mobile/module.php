<?php

/**
 * @project       AlarmanrufNeXXt Mobile
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/AANM_autoload.php';

class AlarmanrufNeXXtMobile extends IPSModule
{
    //Helper
    use AANM_AlarmProtocol;
    use AANM_Config;
    use AANM_Control;
    use AANM_TriggerConditions;

    //Constants
    private const MODULE_NAME = 'Alarmanruf NeXXt Mobile';
    private const MODULE_PREFIX = 'AANM';
    private const MODULE_VERSION = '7.0-1, 24.10.2022';
    private const ALARMPROTOCOL_MODULE_GUID = '{66BDB59B-E80F-E837-6640-005C32D5FC24}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties

        //Functions
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('EnableActive', false);
        $this->RegisterPropertyBoolean('EnableAlarmCall', true);
        $this->RegisterPropertyBoolean('EnableGetCurrentBalance', true);
        $this->RegisterPropertyBoolean('EnableCurrentBalance', true);

        //Alarm call
        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('SenderPhoneNumber', '+49');
        $this->RegisterPropertyInteger('Timeout', 5000);
        $this->RegisterPropertyInteger('AlarmCallSwitchOnDelay', 0);
        $this->RegisterPropertyString('DefaultAnnouncement', 'Hinweis, es wurde ein Alarm ausgelöst!');
        $this->RegisterPropertyString('Recipients', '[]');

        //Trigger list
        $this->RegisterPropertyString('TriggerList', '[]');
        //Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
        //Automatic deactivation
        $this->RegisterPropertyBoolean('UseAutomaticDeactivation', false);
        $this->RegisterPropertyString('AutomaticDeactivationStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('AutomaticDeactivationEndTime', '{"hour":6,"minute":0,"second":0}');

        ########## Variables

        //Active
        $id = @$this->GetIDForIdent('Active');
        $this->RegisterVariableBoolean('Active', 'Aktiv', '~Switch', 10);
        $this->EnableAction('Active');
        if (!$id) {
            $this->SetValue('Active', true);
        }

        //Alarm call
        $id = @$this->GetIDForIdent('AlarmCall');
        $this->RegisterVariableBoolean('AlarmCall', 'Alarmanruf', '~Switch', 20);
        $this->EnableAction('AlarmCall');
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('AlarmCall'), 'Mobile');
        }

        //Get current balance
        $profile = self::MODULE_PREFIX . $this->InstanceID . '.GetCurrentBalance';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Guthaben abfragen', 'Euro', 0x00FF00);
        $this->RegisterVariableInteger('GetCurrentBalance', 'Guthaben abfragen', $profile, 30);
        $this->EnableAction('GetCurrentBalance');

        //Current balance
        $id = @$this->GetIDForIdent('CurrentBalance');
        $this->RegisterVariableString('CurrentBalance', 'Guthaben', '', 40);
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('CurrentBalance'), 'Information');
        }

        ########## Attribute
        $this->RegisterAttributeString('Announcement', '');

        ########## Timers

        $this->RegisterTimer('ActivateAlarmCall', 0, self::MODULE_PREFIX . '_ActivateAlarmCall(' . $this->InstanceID . ');');
        $this->RegisterTimer('GetCurrentBalance', 0, self::MODULE_PREFIX . '_GetCurrentBalance(' . $this->InstanceID . ');');
        $this->RegisterTimer('StartAutomaticDeactivation', 0, self::MODULE_PREFIX . '_StartAutomaticDeactivation(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopAutomaticDeactivation', 0, self::MODULE_PREFIX . '_StopAutomaticDeactivation(' . $this->InstanceID . ',);');
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all update messages
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        //Register references and update messages
        $names = [];
        $names[] = ['propertyName' => 'AlarmProtocol', 'useUpdate' => false];
        foreach ($names as $name) {
            $id = $this->ReadPropertyInteger($name['propertyName']);
            if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                $this->RegisterReference($id);
                if ($name['useUpdate']) {
                    $this->RegisterMessage($id, VM_UPDATE);
                }
            }
        }

        $variables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($variables as $variable) {
            if (!$variable['Use']) {
                continue;
            }
            //Primary condition
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $id = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                            $this->RegisterReference($id);
                            $this->RegisterMessage($id, VM_UPDATE);
                        }
                    }
                }
            }
            //Secondary condition, multi
            if ($variable['SecondaryCondition'] != '') {
                $secondaryConditions = json_decode($variable['SecondaryCondition'], true);
                if (array_key_exists(0, $secondaryConditions)) {
                    if (array_key_exists('rules', $secondaryConditions[0])) {
                        $rules = $secondaryConditions[0]['rules']['variable'];
                        foreach ($rules as $rule) {
                            if (array_key_exists('variableID', $rule)) {
                                $id = $rule['variableID'];
                                if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                                    $this->RegisterReference($id);
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->UnlockSemaphore('ExecuteAlarmCall');

        //WebFront options
        IPS_SetHidden($this->GetIDForIdent('Active'), !$this->ReadPropertyBoolean('EnableActive'));
        IPS_SetHidden($this->GetIDForIdent('AlarmCall'), !$this->ReadPropertyBoolean('EnableAlarmCall'));
        IPS_SetHidden($this->GetIDForIdent('GetCurrentBalance'), !$this->ReadPropertyBoolean('EnableGetCurrentBalance'));
        IPS_SetHidden($this->GetIDForIdent('CurrentBalance'), !$this->ReadPropertyBoolean('EnableCurrentBalance'));

        //Reset
        $this->SetTimerInterval('ActivateAlarmCall', 0);
        $this->SetTimerInterval('GetCurrentBalance', 0);

        $this->SetAutomaticDeactivationTimer();
        $this->CheckAutomaticDeactivationTimer();

        $this->GetCurrentBalance();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Profiles
        $profiles = ['GetCurrentBalance'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                // $Data[0] = actual value
                // $Data[1] = value changed
                // $Data[2] = last value
                // $Data[3] = timestamp actual value
                // $Data[4] = timestamp value changed
                // $Data[5] = timestamp last value

                if ($this->CheckMaintenance()) {
                    return;
                }

                //Check trigger conditions
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                $scriptText = self::MODULE_PREFIX . '_CheckTriggerConditions(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                @IPS_RunScriptText($scriptText);
                break;

        }
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Active':
                $this->SetValue($Ident, $Value);
                if (!$Value) {
                    $this->ToggleAlarmCall(false, '');
                }
                break;

            case 'AlarmCall':
                $this->ToggleAlarmCall($Value, '');
                break;

            case 'GetCurrentBalance':
                $this->GetCurrentBalance();
                break;

        }
    }

    public function CreateAlarmProtocolInstance(): void
    {
        $id = @IPS_CreateInstance(self::ALARMPROTOCOL_MODULE_GUID);
        if (is_int($id)) {
            IPS_SetName($id, 'Alarmprotokoll');
            echo 'Instanz mit der ID ' . $id . ' wurde erfolgreich erstellt!';
        } else {
            echo 'Instanz konnte nicht erstellt werden!';
        }
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function CheckMaintenance(): bool
    {
        $result = false;
        if (!$this->GetValue('Active')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Instanz ist inaktiv!', 0);
            $result = true;
        }
        return $result;
    }
}