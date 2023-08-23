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

    $processed = false;

    foreach ($recipient_emails as $key => $recipient_email) {
        if (strpos($filename, $key) !== false) {
            $pdf = new Fpdi();
            $page_count = $pdf->setSourceFile($pdf_file);

            $output_file1 = $output_dir . str_replace('.pdf', '_Barmenia.pdf', $filename);
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

            $output_file2 = $output_dir . str_replace('.pdf', '_UKV.pdf', $filename);
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

            // Prüfe, ob der Dateiname "900" oder "1100" enthält
            if (strpos($filename, '900') !== false || strpos($filename, '1100') !== false) {
                $output_file3 = $output_dir . str_replace('.pdf', '_RuV.pdf', $filename);
                $pdf = new Fpdi();
                $page_count = $pdf->setSourceFile($pdf_file);

                $pdf->AddPage();
                for ($i = 12; $i <= 20; $i++) {
                    $tplIdx = $pdf->importPage($i);
                    $pdf->useTemplate($tplIdx);
                    if ($i != 20) {
                        $pdf->AddPage();
                    }
                }
                $pdf->Output($output_file3, 'F');
                $pdf->Close();

                // Verschiebe die Ausgabedatei für Seiten 12-20 in den Ordner "fertig"
                rename($output_file3, $finished_dir . basename($output_file3));

                // Füge die Datei für Seiten 12-20 den Anhängen hinzu
                $attachments[] = $finished_dir . basename($output_file3);
            }

            // Verschiebe die Ausgabedateien für Seiten 1-2 und 3-10 in den Ordner "fertig"
            rename($output_file1, $finished_dir . basename($output_file1));
            rename($output_file2, $finished_dir . basename($output_file2));

            // Füge die Dateien für Seiten 1-2 und 3-10 den Anhängen hinzu
            $attachments[] = $finished_dir . basename($output_file1);
            $attachments[] = $finished_dir . basename($output_file2);

            // Verschiebe die verarbeiteten Ausgangsdateien in den Ordner "processed"
            $processed_path = $processed_dir . basename($pdf_file);
            $i = 1;

            while (file_exists($processed_path)) {
                $processed_path = $processed_dir . str_replace('.pdf', "_{$i}.pdf", $filename);
                $i++;
            }

            rename($pdf_file, $processed_path);

            $processed = true;
        }
    }

    // Überprüfe, ob Anhänge vorhanden sind, die versendet werden müssen
    if ($processed && !empty($attachments)) {
        sendEmail($recipient_email, $attachments);
        $attachments = []; // Leere das Array für den nächsten Durchlauf
    }
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
