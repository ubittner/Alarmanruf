<?php

/**
 * @project       Alarmanruf/Alarmanruf
 * @file          AA_Config.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait AA_Config
{
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

        //Info
        $form['elements'][0] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Info',
            'items'   => [
                [
                    'type'    => 'Label',
                    'name'    => 'ModuleID',
                    'caption' => "ID:\t\t\t" . $this->InstanceID
                ],
                [
                    'type'    => 'Label',
                    'name'    => 'ModuleDesignation',
                    'caption' => "Modul:\t\t" . self::MODULE_NAME
                ],
                [
                    'type'    => 'Label',
                    'name'    => 'ModulePrefix',
                    'caption' => "Präfix:\t\t" . self::MODULE_PREFIX
                ],
                [
                    'type'    => 'Label',
                    'name'    => 'ModuleVersion',
                    'caption' => "Version:\t\t" . self::MODULE_VERSION
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

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Funktionen',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableActive',
                    'caption' => 'Aktiv (Schalter im WebFront)'
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmCall',
                    'caption' => 'Alarmanruf'
                ]
            ]
        ];

        //Alarm call
        $alarmCall = $this->ReadPropertyInteger('AlarmCall');
        $enableAlarmCallButton = false;
        if ($alarmCall > 1 && @IPS_ObjectExists($alarmCall)) { //0 = main category, 1 = none
            $enableAlarmCallButton = true;
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Alarmanruf',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectVariable',
                            'name'     => 'AlarmCall',
                            'caption'  => 'Variable',
                            'width'    => '600px',
                            'onChange' => self::MODULE_PREFIX . '_ModifyButton($id, "AlarmCallConfigurationButton", "ID " . $AlarmCall . " bearbeiten", $AlarmCall);'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'name'     => 'AlarmCallConfigurationButton',
                            'caption'  => 'ID ' . $alarmCall . ' bearbeiten',
                            'visible'  => $enableAlarmCallButton,
                            'objectID' => $alarmCall
                        ]
                    ]
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'AlarmCallSwitchingDelay',
                    'caption' => 'Schaltverzögerung',
                    'minimum' => 0,
                    'suffix'  => 'Millisekunden'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'AlarmCallSwitchOnDelay',
                    'caption' => 'Einschaltverzögerung',
                    'minimum' => 0,
                    'suffix'  => 'Sekunden'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'AlarmCallSwitchOnDuration',
                    'caption' => 'Impulsdauer',
                    'minimum' => 0,
                    'maximum' => 30,
                    'suffix'  => 'Sekunden'
                ]
            ]
        ];

        //Trigger list
        $triggerListValues = [];
        $variables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($variables as $variable) {
            $rowColor = '#C0FFC0'; //light green
            if (!$variable['Use']) {
                $rowColor = '#DFDFDF'; //grey
            }
            //Primary condition
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $id = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        if ($id <= 1 || !@IPS_ObjectExists($id)) { //0 = main category, 1 = none
                            $rowColor = '#FFC0C0'; //red
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
                                if ($id <= 1 || !@IPS_ObjectExists($id)) { //0 = main category, 1 = none
                                    $rowColor = '#FFC0C0'; //red
                                }
                            }
                        }
                    }
                }
            }
            $triggerListValues[] = ['rowColor' => $rowColor];
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Auslöser',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'TriggerList',
                    'rowCount' => 15,
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
                            'width'   => '300px',
                            'add'     => 2,
                            'visible' => false,
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => '0 - Alarmanruf beenden (Aus)',
                                        'value'   => 0
                                    ],
                                    [
                                        'caption' => '1 - Alarmanruf auslösen (An)',
                                        'value'   => 1
                                    ],
                                    [
                                        'caption' => '2 - Keine Funktion',
                                        'value'   => 2
                                    ]
                                ]
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
        if ($alarmProtocol > 1 && @IPS_ObjectExists($alarmProtocol)) { //0 = main category, 1 = none
            $enableAlarmProtocolButton = true;
        }

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
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
                            'onChange' => self::MODULE_PREFIX . '_ModifyButton($id, "AlarmProtocolConfigurationButton", "ID " . $AlarmProtocol . " Instanzkonfiguration", $AlarmProtocol);'
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Neue Instanz erstellen',
                            'onClick' => self::MODULE_PREFIX . '_CreateAlarmProtocolInstance($id);'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $alarmProtocol . ' Instanzkonfiguration',
                            'name'     => 'AlarmProtocolConfigurationButton',
                            'visible'  => $enableAlarmProtocolButton,
                            'objectID' => $alarmProtocol
                        ]
                    ]
                ]
            ]
        ];

        //Command control
        $commandControl = $this->ReadPropertyInteger('CommandControl');
        $enableButton = false;
        if ($commandControl > 1 && @IPS_ObjectExists($commandControl)) { //0 = main category, 1 = none
            $enableButton = true;
        }
        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Ablaufsteuerung',
            'items'   => [
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'CommandControl',
                            'caption'  => 'Instanz',
                            'moduleID' => self::ABLAUFSTEUERUNG_MODULE_GUID,
                            'width'    => '600px',
                            'onChange' => self::MODULE_PREFIX . '_ModifyButton($id, "CommandControlConfigurationButton", "ID " . $CommandControl . " Instanzkonfiguration", $CommandControl);'
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Neue Instanz erstellen',
                            'onClick' => self::MODULE_PREFIX . '_CreateCommandControlInstance($id);'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $commandControl . ' Instanzkonfiguration',
                            'name'     => 'CommandControlConfigurationButton',
                            'visible'  => $enableButton,
                            'objectID' => $commandControl
                        ]
                    ]
                ]
            ]
        ];

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
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

        ########## Actions

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Konfiguration',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Neu laden',
                    'onClick' => self::MODULE_PREFIX . '_ReloadConfig($id);'
                ]
            ]
        ];

        //Test center
        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Schaltfunktionen',
            'items'   => [
                [
                    'type' => 'TestCenter',
                ]
            ]
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

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Registrierte Referenzen',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'RegisteredReferences',
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
                ]
            ]
        ];

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

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Registrierte Nachrichten',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'RegisteredMessages',
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

        ########## Status

        $form['status'][] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => self::MODULE_NAME . ' wird erstellt',
        ];
        $form['status'][] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => self::MODULE_NAME . ' ist aktiv',
        ];
        $form['status'][] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => self::MODULE_NAME . ' wird gelöscht',
        ];
        $form['status'][] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => self::MODULE_NAME . ' ist inaktiv',
        ];
        $form['status'][] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug!',
        ];

        return json_encode($form);
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
        if ($ObjectID > 1 && @IPS_ObjectExists($ObjectID)) { //0 = main category, 1 = none
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
                if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                    $state = true;
                }
            }
        }
        $this->UpdateFormField($Field, 'caption', 'ID ' . $id . ' Bearbeiten');
        $this->UpdateFormField($Field, 'visible', $state);
        $this->UpdateFormField($Field, 'objectID', $id);
    }

    /**
     * Reloads the configuration form.
     *
     * @return void
     */
    public function ReloadConfig(): void
    {
        $this->ReloadForm();
    }
}