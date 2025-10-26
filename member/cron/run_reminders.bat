@echo off
set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=C:\xampp\htdocs\swks\member\cron\send_due_reminders.php"
set "LOG=C:\xampp\htdocs\swks\member\cron\logs\reminder.log"

REM run the PHP script and append output to a log
"%PHP%" -f "%SCRIPT%" >> "%LOG%" 2>&1
