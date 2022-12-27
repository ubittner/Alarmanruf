# Alarmanruf NeXXt Mobile

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.


### Inhaltsverzeichnis

1. [Modulbeschreibung](#1-modulbeschreibung)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Schaubild](#3-schaubild)
4. [Auslöser](#4-auslöser)
5. [Externe Aktion](#5-externe-aktion)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz)
    1. [Alarmanruf schalten](#61-Alarmanruf-schalten)


### 1. Modulbeschreibung

Dieses Modul löst einen Alarmanruf über NeXXt Mobile in [IP-Symcon](https://www.symcon.de) aus.

### 2. Voraussetzungen

- IP-Symcon ab Version 6.1

### 3. Schaubild

```
                       +---------------------------------+
Auslöser <-------------+ Alarmanruf NeXXt Mobile (Modul) |<------------- externe Aktion
                       |                                 |
                       | Rufnummer 1                     |
                       | Rufnummer 2                     |
                       | Rufnummer 3                     |
                       | Rufnummer n                     |
                       |                                 |
                       +----------------+----------------+
                                        |  
                                        |
                                        |                       
                                        v                    
                              +--------------------+               
                              | NeXXt Mobile (API) |
                              +--------------------+
```

### 4. Auslöser

Das Modul Alarmanruf NeXXt Mobile reagiert auf verschiedene Auslöser.  

### 5. Externe Aktion

Das Modul Alarmanruf NeXXt Mobile kann über eine externe Aktion geschaltet werden.  
Nachfolgendes Beispiel beendet einen Alarmanruf.

> AANM_ToggleAlarmCall(12345, false, '');

### 6. PHP-Befehlsreferenz

#### 6.1 Alarmanruf schalten

```
boolean AANM_ToggleAlarmCall(integer INSTANCE_ID, boolean STATE, string ANNOUNCEMENT);
```

Konnte der Befehl erfolgreich ausgeführt werden, liefert er als Ergebnis **TRUE**, andernfalls **FALSE**.

| Parameter      | Wert  | Bezeichnung    |
|----------------|-------|----------------|
| `INSTANCE_ID`  |       | ID der Instanz |
| `STATE`        | false | Aus            |
|                | true  | An             |
| `ANNOUNCEMENT` |       | Ansage         |

Beispiel:
> AANM_ToggleAlarmCall(12345, false, 'Es wurde ein Alarm ausgelöst!');
