<?php
echo "<p>__DIR__ = ".__DIR__."</p>";
echo "<p>Root phpMailer? ".(file_exists(__DIR__."/phpmailer/src/PHPMailer.php")?'YES':'NO')."</p>";
echo "<p>swks/phpMailer? ".(file_exists(__DIR__."/swks/phpmailer/src/PHPMailer.php")?'YES':'NO')."</p>";
