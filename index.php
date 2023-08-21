<?php

require_once 'vendor/autoload.php';
require_once 'config.php';

use setasign\Fpdi\Fpdi;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdfs_dir = 'pdfs/';
$output_dir = 'output/';
$finished_dir = 'fertig/';
$processed_dir = 'processed/';

if (!file_exists($output_dir)) {
    mkdir($output_dir);
}

if (!file_exists($finished_dir)) {
    mkdir($finished_dir);
}

if (!file_exists($processed_dir)) {
    mkdir($processed_dir);
}

$attachments = []; // Array für die gesammelten Anhänge

foreach (glob($pdfs_dir . '*.pdf') as $pdf_file) {
    $filename = basename($pdf_file);

    foreach ($recipient_emails as $key => $recipient_email) {
        if (strpos($filename, $key) !== false) {
            $pdf = new Fpdi();
            $page_count = $pdf->setSourceFile($pdf_file);

            $output_file1 = $output_dir . str_replace('.pdf', '_part1.pdf', $filename);
            $pdf->AddPage();
            $tplIdx = $pdf->importPage(1);
            $pdf->useTemplate($tplIdx);
            $pdf->AddPage();
            $tplIdx = $pdf->importPage(2);
            $pdf->useTemplate($tplIdx);
            $pdf->Output($output_file1, 'F');
            $pdf->Close();

            $pdf = new Fpdi();
            $page_count = $pdf->setSourceFile($pdf_file);

            $output_file2 = $output_dir . str_replace('.pdf', '_part2.pdf', $filename);
            $pdf->AddPage();
            for ($i = 3; $i <= 10; $i++) {
                $tplIdx = $pdf->importPage($i);
                $pdf->useTemplate($tplIdx);
                if ($i != 10) {
                    $pdf->AddPage();
                }
            }
            $pdf->Output($output_file2, 'F');
            $pdf->Close();

            // Verschiebe die Ausgabedateien in den Ordner "fertig"
            rename($output_file1, $finished_dir . basename($output_file1));
            rename($output_file2, $finished_dir . basename($output_file2));

            // Sende die verschobenen Dateien per E-Mail, aber sammle die Anhänge
            $attachments[] = $finished_dir . basename($output_file1);
            $attachments[] = $finished_dir . basename($output_file2);

            // Überprüfe, ob die maximale Anzahl von Anhängen erreicht wurde
            if (count($attachments) >= $config['max_attachments']) {
                sendEmail($recipient_email, $attachments);
                $attachments = []; // Leere das Array für den nächsten Durchlauf
            }
        }
    }
}

// Überprüfe, ob noch Anhänge übrig sind, die versendet werden müssen
if (!empty($attachments)) {
    sendEmail($recipient_email, $attachments);
}

// Verschiebe die verarbeiteten Ausgangsdateien in den Ordner "processed"
foreach (glob($finished_dir . '*.pdf') as $finished_file) {
    $filename = basename($finished_file);
    $target_path = $processed_dir . $filename;
    $i = 1;

    while (file_exists($target_path)) {
        $target_path = $processed_dir . str_replace('.pdf', "_{$i}.pdf", $filename);
        $i++;
    }

    rename($finished_file, $target_path);
}

function sendEmail($recipient, $attachments) {
    global $config;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port = $config['smtp_port'];

        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($recipient);

        foreach ($attachments as $attachmentPath) {
            $mail->addAttachment($attachmentPath);
        }

        $mail->Subject = 'PDF Attachments';
        $mail->Body = 'Please find the attached PDF files.';

        $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

?>
