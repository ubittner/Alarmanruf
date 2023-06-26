<?php

/**
 * @project       AlarmanrufVoIP
 * @file          AAVOIP_Config.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait AAVOIP_Config
{
    /**
     * Reloads the configuration form.
     *
     * @return void
     */
    public function ReloadConfig(): void
    {
        $this->ReloadForm();
    }

    /**
     * Expands or collapses the expansion panels.
     *
     * @param bool $State
     * false =  collapse,
     * true =   expand
     *
     * @return void
     */
    public function ExpandExpansionPanels(bool $State): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->UpdateFormField('Panel' . $i, 'expanded', $State);
        }
    }

    /**
     * Modifies a configuration button.
     *
     * @param string $Field
     * @param string $Caption
     * @param int $ObjectID
     * @return void
     */
    public function ModifyButton(string $Field, string $Caption, int $ObjectID): void
    {
        $state = false;
        if ($ObjectID > 1 && @IPS_ObjectExists($ObjectID)) {
            $state = true;
        }
        $this->UpdateFormField($Field, 'caption', $Caption);
        $this->UpdateFormField($Field, 'visible', $state);
        $this->UpdateFormField($Field, 'objectID', $ObjectID);
    }

    /**
     * Modifies a trigger list configuration button
     *
     * @param string $Field
     * @param string $Condition
     * @return void
     */
    public function ModifyTriggerListButton(string $Field, string $Condition): void
    {
        $id = 0;
        $state = false;
        //Get variable id
        $primaryCondition = json_decode($Condition, true);
        if (array_key_exists(0, $primaryCondition)) {
            if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                $id = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                if ($id > 1 && @IPS_ObjectExists($id)) {
                    $state = true;
                }
            }
        }
        $this->UpdateFormField($Field, 'caption', 'ID ' . $id . ' Bearbeiten');
        $this->UpdateFormField($Field, 'visible', $state);
        $this->UpdateFormField($Field, 'objectID', $id);
    }

    /**
     * Gets the configuration form.
     *
     * @return false|string
     * @throws Exception
     */
    public function GetConfigurationForm()
    {
        $form = [];

        ########## Elements

        //Configuration buttons
        $form['elements'][0] =
            [
                'type'  => 'RowLayout',
                'items' => [
                    [
                        'type'    => 'Button',
                        'caption' => 'Konfiguration ausklappen',
                        'onClick' => self::MODULE_PREFIX . '_ExpandExpansionPanels($id, true);'
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Konfiguration einklappen',
                        'onClick' => self::MODULE_PREFIX . '_ExpandExpansionPanels($id, false);'
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Konfiguration neu laden',
                        'onClick' => self::MODULE_PREFIX . '_ReloadConfig($id);'
                    ]
                ]
            ];

        //Info
        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $module = IPS_GetModule(self::MODULE_GUID);
        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel1',
            'caption' => 'Info',
            'items'   => [
                [
                    'type'    => 'Label',
                    'name'    => 'ModuleID',
                    'caption' => "ID:\t\t\t" . $this->InstanceID
                ],
                [
                    'type'    => 'Label',
                    'caption' => "Modul:\t\tAlarmanruf VoIP"
                ],
                [
                    'type'    => 'Label',
                    'caption' => "Präfix:\t\t" . $module['Prefix']
                ],
                [
                    'type'    => 'Label',
                    'caption' => "Version:\t\t" . $library['Version'] . '-' . $library['Build'] . ', ' . date('d.m.Y', $library['Date'])
                ],
                [
                    'type'    => 'Label',
                    'caption' => "Entwickler:\t" . $library['Author']
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'Note',
                    'caption' => 'Notiz',
                    'width'   => '600px'
                ]
            ]
        ];

        //VoIP
        $voip = $this->ReadPropertyInteger('VoIP');
        $enableVoIPButton = false;
        if ($voip > 1 && @IPS_ObjectExists($voip)) {
            $enableVoIPButton = true;
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel2',
            'caption' => 'VoIP',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'VoIP',
                            'caption'  => 'Instanz',
                            'width'    => '600px',
                            'moduleID' => self::VOIP_MODULE_GUID,
                            'onChange' => self::MODULE_PREFIX . '_ModifyButton($id, "VoIPConfigurationButton", "ID " . $VoIP . " konfigurieren", $VoIP);'
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'name'     => 'VoIPConfigurationButton',
                            'caption'  => 'ID ' . $voip . ' konfigurieren',
                            'visible'  => $enableVoIPButton,
                            'objectID' => $voip
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Neue Instanz erstellen',
                            'onClick' => self::MODULE_PREFIX . '_CreateVoIPInstance($id);'
                        ]
                    ]
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'AlarmCallDelay',
                    'caption' => 'Anrufverzögerung',
                    'minimum' => 0,
                    'suffix'  => 'Sekunden'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'VoIPDuration',
                    'caption' => 'Verbindungsdauer',
                    'minimum' => 10,
                    'maximum' => 25,
                    'suffix'  => 'Sekunden'
                ]
            ]
        ];

        //Text to speech
        $tts = $this->ReadPropertyInteger('TTSAWSPolly');
        $enableTTSButton = false;
        if ($tts > 1 && @IPS_ObjectExists($tts)) {
            $enableTTSButton = true;
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel3',
            'caption' => 'Text to Speech',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'TTSAWSPolly',
                            'caption'  => 'Text to Speech (AWS Polly)',
                            'width'    => '600px',
                            'moduleID' => self::TTSAWSPOLLY_MODULE_GUID,
                            'onChange' => self::MODULE_PREFIX . '_ModifyButton($id, "TTSConfigurationButton", "ID " . $TTSAWSPolly . " konfigurieren", $TTSAWSPolly);'
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'name'     => 'TTSConfigurationButton',
                            'caption'  => 'ID ' . $tts . ' konfigurieren',
                            'visible'  => $enableTTSButton,
                            'objectID' => $tts
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Neue Instanz erstellen',
                            'onClick' => self::MODULE_PREFIX . '_CreateTTSAWSPollyInstance($id);'
                        ]
                    ]
                ]
            ]
        ];

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel4',
            'caption' => 'Anrufliste',
            'items'   => [
                [
                    'type'      => 'ValidationTextBox',
                    'name'      => 'DefaultAnnouncement',
                    'caption'   => 'Standardansage',
                    'width'     => '600px',
                    'multiline' => true
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'     => 'List',
                    'name'     => 'Recipients',
                    'caption'  => 'Telefonnummern',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'Name',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'caption' => 'Aktiviert',
                            'name'    => 'Use',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => 'Name',
                            'name'    => 'Name',
                            'width'   => '400px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Rufnummer',
                            'name'    => 'PhoneNumber',
                            'width'   => '300px',
                            'add'     => '+49',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        //Trigger list
        $triggerListValues = [];
        $variables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($variables as $variable) {
            $sensorID = 0;
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $sensorID = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                    }
                }
            }
            //Check conditions first
            $conditions = true;
            if ($sensorID <= 1 || !@IPS_ObjectExists($sensorID)) {
                $conditions = false;
            }
            if ($variable['SecondaryCondition'] != '') {
                $secondaryConditions = json_decode($variable['SecondaryCondition'], true);
                if (array_key_exists(0, $secondaryConditions)) {
                    if (array_key_exists('rules', $secondaryConditions[0])) {
                        $rules = $secondaryConditions[0]['rules']['variable'];
                        foreach ($rules as $rule) {
                            if (array_key_exists('variableID', $rule)) {
                                $id = $rule['variableID'];
                                if ($id <= 1 || !@IPS_ObjectExists($id)) {
                                    $conditions = false;
                                }
                            }
                        }
                    }
                }
            }
            $stateName = 'fehlerhaft';
            $rowColor = '#FFC0C0'; //red
            if ($conditions) {
                $stateName = 'Bedingung nicht erfüllt!';
                $rowColor = '#C0C0FF'; //violett
                if (IPS_IsConditionPassing($variable['PrimaryCondition']) && IPS_IsConditionPassing($variable['SecondaryCondition'])) {
                    $stateName = 'Bedingung erfüllt';
                    $rowColor = '#C0FFC0'; //light green
                }
                if (!$variable['Use']) {
                    $stateName = 'Deaktiviert';
                    $rowColor = '#DFDFDF'; //grey
                }
            }
            $triggerListValues[] = ['ActualStatus' => $stateName, 'SensorID' => $sensorID, 'rowColor' => $rowColor];
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel5',
            'caption' => 'Auslöser',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'TriggerList',
                    'caption'  => 'Auslöser',
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'columns'  => [
                        [
                            'caption' => 'Aktiviert',
                            'name'    => 'Use',
                            'width'   => '100px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'ActualStatus',
                            'caption' => 'Aktueller Status',
                            'width'   => '200px',
                            'add'     => ''
                        ],
                        [
                            'caption' => 'ID',
                            'name'    => 'SensorID',
                            'onClick' => self::MODULE_PREFIX . '_ModifyTriggerListButton($id, "TriggerListConfigurationButton", $TriggerList["PrimaryCondition"]);',
                            'width'   => '100px',
                            'add'     => ''
                        ],
                        [
                            'caption' => 'Bezeichnung',
                            'name'    => 'Designation',
                            'onClick' => self::MODULE_PREFIX . '_ModifyTriggerListButton($id, "TriggerListConfigurationButton", $TriggerList["PrimaryCondition"]);',
                            'width'   => '400px',
                            'add'     => '',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'SpacerPrimaryCondition',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'caption' => 'Bedingung:',
                            'name'    => 'LabelPrimaryCondition',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'   => 'Label',
                                'italic' => true,
                                'bold'   => true
                            ]
                        ],
                        [
                            'caption' => 'Mehrfachauslösung',
                            'name'    => 'UseMultipleAlerts',
                            'width'   => '200px',
                            'add'     => false,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'PrimaryCondition',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'SelectCondition'
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'SpacerSecondaryCondition',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'caption' => 'Weitere Bedingung(en):',
                            'name'    => 'LabelSecondaryCondition',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'   => 'Label',
                                'italic' => true,
                                'bold'   => true
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'SecondaryCondition',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'  => 'SelectCondition',
                                'multi' => true
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'SpacerAlarmCallAction',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'caption' => 'Alarmanruf:',
                            'name'    => 'LabelAlarmCall',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'   => 'Label',
                                'italic' => true,
                                'bold'   => true
                            ]
                        ],
                        [
                            'caption' => 'Alarmanruf',
                            'name'    => 'AlarmCallAction',
                            'width'   => '250px',
                            'add'     => 2,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Alarmanruf beenden (Aus)',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => 'Alarmanruf auslösen (An)',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => 'Keine Funktion',
                                        'value'   => 2
                                    ]
                                ]
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'SpacerTriggeringDetector',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'caption' => 'Auslösender Melder:',
                            'name'    => 'LabelTriggeringDetector',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'   => 'Label',
                                'italic' => true,
                                'bold'   => true
                            ]
                        ],
                        [
                            'caption' => 'Auslösender Melder:',
                            'name'    => 'TriggeringDetector',
                            'width'   => '200px',
                            'add'     => 0,
                            'visible' => false,
                            'edit'    => [
                                'type' => 'SelectVariable'
                            ]
                        ],
                        [
                            'caption' => ' ',
                            'name'    => 'SpacerAnnouncement',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'Label'
                            ]
                        ],
                        [
                            'caption' => 'Ansagetext:',
                            'name'    => 'LabelAnnouncement',
                            'width'   => '200px',
                            'add'     => '',
                            'visible' => false,
                            'edit'    => [
                                'type'   => 'Label',
                                'italic' => true,
                                'bold'   => true
                            ]
                        ],
                        [
                            'caption' => 'Ansagetext',
                            'name'    => 'Announcement',
                            'width'   => '800px',
                            'add'     => 'Hinweis %1$s hat einen Alarm ausgelöst. Ich wiederhole %1$s hat einen Alarm ausgelöst.',
                            'visible' => false,
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ]
                    ],
                    'values' => $triggerListValues,
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'TriggerListConfigurationButton',
                    'caption'  => 'Bearbeiten',
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        //Alarm protocol
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        $enableAlarmProtocolButton = false;
        if ($alarmProtocol > 1 && @IPS_ObjectExists($alarmProtocol)) {
            $enableAlarmProtocolButton = true;
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel6',
            'caption' => 'Alarmprotokoll',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'AlarmProtocol',
                            'caption'  => 'Instanz',
                            'moduleID' => self::ALARMPROTOCOL_MODULE_GUID,
                            'width'    => '600px',
                            'onChange' => self::MODULE_PREFIX . '_ModifyButton($id, "AlarmProtocolConfigurationButton", "ID " . $AlarmProtocol . " konfigurieren", $AlarmProtocol);'
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $alarmProtocol . ' konfigurieren',
                            'name'     => 'AlarmProtocolConfigurationButton',
                            'visible'  => $enableAlarmProtocolButton,
                            'objectID' => $alarmProtocol
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Neue Instanz erstellen',
                            'onClick' => self::MODULE_PREFIX . '_CreateAlarmProtocolInstance($id);'
                        ]
                    ]
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'Location',
                    'caption' => 'Standortbezeichnung (z.B. Musterstraße 1)',
                    'width'   => '600px'
                ]
            ]
        ];

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel7',
            'caption' => 'Deaktivierung',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'UseAutomaticDeactivation',
                    'caption' => 'Automatische Deaktivierung'
                ],
                [
                    'type'    => 'SelectTime',
                    'name'    => 'AutomaticDeactivationStartTime',
                    'caption' => 'Startzeit'
                ],
                [
                    'type'    => 'SelectTime',
                    'name'    => 'AutomaticDeactivationEndTime',
                    'caption' => 'Endzeit'
                ]
            ]
        ];

        //Visualisation
        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'name'    => 'Panel8',
            'caption' => 'Visualisierung',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableActive',
                    'caption' => 'Aktiv'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmCall',
                    'caption' => 'Alarmanruf'
                ]
            ]
        ];

        ########## Actions

        //Test center
        $form['actions'][] =
            [
                'type' => 'TestCenter'
            ];

        $form['actions'][] =
            [
                'type'    => 'Label',
                'caption' => ' '
            ];

        //Registered references
        $registeredReferences = [];
        $references = $this->GetReferenceList();
        foreach ($references as $reference) {
            $name = 'Objekt #' . $reference . ' existiert nicht';
            $rowColor = '#FFC0C0'; //red
            if (@IPS_ObjectExists($reference)) {
                $name = IPS_GetName($reference);
                $rowColor = '#C0FFC0'; //light green
            }
            $registeredReferences[] = [
                'ObjectID' => $reference,
                'Name'     => $name,
                'rowColor' => $rowColor];
        }

        //Registered messages
        $registeredMessages = [];
        $messages = $this->GetMessageList();
        foreach ($messages as $id => $messageID) {
            $name = 'Objekt #' . $id . ' existiert nicht';
            $rowColor = '#FFC0C0'; //red
            if (@IPS_ObjectExists($id)) {
                $name = IPS_GetName($id);
                $rowColor = '#C0FFC0'; //light green
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $registeredMessages[] = [
                'ObjectID'           => $id,
                'Name'               => $name,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription,
                'rowColor'           => $rowColor];
        }

        //Developer area
        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Entwicklerbereich',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'RegisteredReferences',
                    'caption'  => 'Registrierte Referenzen',
                    'rowCount' => 10,
                    'sort'     => [
                        'column'    => 'ObjectID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name'    => 'ObjectID',
                            'width'   => '150px',
                            'onClick' => self::MODULE_PREFIX . '_ModifyButton($id, "RegisteredReferencesConfigurationButton", "ID " . $RegisteredReferences["ObjectID"] . " aufrufen", $RegisteredReferences["ObjectID"]);'
                        ],
                        [
                            'caption' => 'Name',
                            'name'    => 'Name',
                            'width'   => '300px',
                            'onClick' => self::MODULE_PREFIX . '_ModifyButton($id, "RegisteredReferencesConfigurationButton", "ID " . $RegisteredReferences["ObjectID"] . " aufrufen", $RegisteredReferences["ObjectID"]);'
                        ]
                    ],
                    'values' => $registeredReferences
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'RegisteredReferencesConfigurationButton',
                    'caption'  => 'Aufrufen',
                    'visible'  => false,
                    'objectID' => 0
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'     => 'List',
                    'name'     => 'RegisteredMessages',
                    'caption'  => 'Registrierte Nachrichten',
                    'rowCount' => 10,
                    'sort'     => [
                        'column'    => 'ObjectID',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'caption' => 'ID',
                            'name'    => 'ObjectID',
                            'width'   => '150px',
                            'onClick' => self::MODULE_PREFIX . '_ModifyButton($id, "RegisteredMessagesConfigurationButton", "ID " . $RegisteredMessages["ObjectID"] . " aufrufen", $RegisteredMessages["ObjectID"]);'
                        ],
                        [
                            'caption' => 'Name',
                            'name'    => 'Name',
                            'width'   => '300px',
                            'onClick' => self::MODULE_PREFIX . '_ModifyButton($id, "RegisteredMessagesConfigurationButton", "ID " . $RegisteredMessages["ObjectID"] . " aufrufen", $RegisteredMessages["ObjectID"]);'
                        ],
                        [
                            'caption' => 'Nachrichten ID',
                            'name'    => 'MessageID',
                            'width'   => '150px'
                        ],
                        [
                            'caption' => 'Nachrichten Bezeichnung',
                            'name'    => 'MessageDescription',
                            'width'   => '250px'
                        ]
                    ],
                    'values' => $registeredMessages
                ],
                [
                    'type'     => 'OpenObjectButton',
                    'name'     => 'RegisteredMessagesConfigurationButton',
                    'caption'  => 'Aufrufen',
                    'visible'  => false,
                    'objectID' => 0
                ]
            ]
        ];

        //Dummy info message
        $form['actions'][] =
            [
                'type'    => 'PopupAlert',
                'name'    => 'InfoMessage',
                'visible' => false,
                'popup'   => [
                    'closeCaption' => 'OK',
                    'items'        => [
                        [
                            'type'    => 'Label',
                            'name'    => 'InfoMessageLabel',
                            'caption' => '',
                            'visible' => true
                        ]
                    ]
                ]
            ];

        ########## Status

        $form['status'][] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => 'Alarmanruf VoIP wird erstellt',
        ];
        $form['status'][] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => 'Alarmanruf VoIP ist aktiv',
        ];
        $form['status'][] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => 'Alarmanruf VoIP wird gelöscht',
        ];
        $form['status'][] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => 'Alarmanruf VoIP ist inaktiv',
        ];
        $form['status'][] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug!',
        ];

        return json_encode($form);
    }
}