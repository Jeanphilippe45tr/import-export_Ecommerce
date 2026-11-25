<?php
declare(strict_types=1);

/**
 * API : génération, téléchargement et annulation de factures A4 PDF.
 * Dépendance : dompdf/dompdf (composer install).
 *
 * Actions :
 *   pdf&order_id=X   : génère (ou régénère l'aperçu) la facture d'une commande, crée/retourne le PDF
 *   pdf&invoice_id=X : télécharge/affiche une facture existante
 *   annuler&invoice_id=X : annule la facture avec émission d'un avoir (jamais de modification de facture émise)
 */

require_once __DIR__ . '/../../includes/config_bootstrap.php';
require_once __DIR__ . '/../../includes/invoice_pdf.php';

header('Content-Type: application/json; charset=utf-8');

function faError(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_id'])) {
    faError(403, 'Connexion admin requise.');
}

$db = db();
$settings = loadSettings($db);
$action = $_GET['action'] ?? '';

$csrf = $_GET['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
if ($action === 'annuler' && !csrfCheck($csrf)) {
    faError(403, 'Jeton CSRF invalide.');
}

try {
    // ----------------------------------------------------------------
    // ANNULATION DE FACTURE AVEC AVOIR
    // ----------------------------------------------------------------
    if ($action === 'annuler') {
        $invoiceId = (int)($_POST['invoice_id'] ?? ($_GET['invoice_id'] ?? 0));
        if ($invoiceId <= 0) {
            faError(400, 'Facture invalide.');
        }

        $stmt = $db->prepare("SELECT i.*, o.order_number, o.customer_id FROM invoices i JOIN orders o ON o.id = i.order_id WHERE i.id = ? LIMIT 1");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        if ($invoice === false) {
            faError(404, 'Facture introuvable.');
        }
        if (($invoice['status'] ?? '') !== 'émise') {
            faError(400, 'Cette facture est déjà annulée.');
        }

        // Numéro d'avoir continu AV-YYYY-0001
        $datePart = gmdate('Ymd');
        $stmt2 = $db->prepare("SELECT credit_number FROM credit_notes WHERE credit_number LIKE ? ORDER BY credit_number DESC LIMIT 1");
        $stmt2->execute(['AV-' . $datePart . '-%']);
        $lastCn = $stmt2->fetch();
        $seq = 1;
        if ($lastCn !== false) {
            $parts = explode('-', $lastCn['credit_number']);
            $seq = (int)($parts[2] ?? 0) + 1;
        }
        $creditNumber = 'AV-' . $datePart . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        try {
            // Marquer la facture annulée
            $db->prepare("UPDATE invoices SET status = 'annulée', updated_at = NOW() WHERE id = ?")->execute([$invoiceId]);

            // Créer l'avoir
            $db->prepare(
                "INSERT INTO credit_notes (credit_number, invoice_id, credit_date, amount_ht, vat_amount, total_ttc, reason, status, created_at)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?, 'émis', NOW())"
            )->execute([
                $creditNumber,
                $invoiceId,
                (float)$invoice['subtotal_ht'],
                (float)$invoice['vat_amount'],
                (float)$invoice['total_ttc'],
                $invoice['invoice_number'],
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        echo json_encode([
            'ok' => true,
            'credit_number' => $creditNumber,
            'message' => 'Facture annulée. Avoir ' . $creditNumber . ' émis.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----------------------------------------------------------------
    // GÉNÉRATION / TÉLÉCHARGEMENT PDF
    // ----------------------------------------------------------------
    if ($action === 'pdf') {
        $orderId = (int)($_GET['order_id'] ?? 0);
        $invoiceId = (int)($_GET['invoice_id'] ?? 0);
        $download = isset($_GET['download']) ? true : false;

        $order = null;
        $invoice = null;
        $items = [];

        if ($invoiceId > 0) {
            // Facture existante : on ne modifie rien (données figées)
            $stmt = $db->prepare("SELECT i.*, o.order_number, o.customer_id, o.currency_code FROM invoices i JOIN orders o ON o.id = i.order_id WHERE i.id = ? LIMIT 1");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if ($invoice === false) {
                faError(404, 'Facture introuvable.');
            }
            $orderId = (int)$invoice['order_id'];

            $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
            $stmt->execute([$invoiceId]);
            $items = $stmt->fetchAll();

            // Récupérer les infos commande pour le client
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
        } elseif ($orderId > 0) {
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            if ($order === false) {
                faError(404, 'Commande introuvable.');
            }

            // Vérifier si une facture existe déjà pour cette commande
            $stmt = $db->prepare("SELECT id FROM invoices WHERE order_id = ? AND status = 'émise' LIMIT 1");
            $stmt->execute([$orderId]);
            $existing = $stmt->fetch();

            if ($existing !== false) {
                // Facture déjà émise : on la réaffiche (données figées, jamais modifiée)
                $invoiceId = (int)$existing['id'];
                $stmt = $db->prepare("SELECT i.* FROM invoices i WHERE i.id = ? LIMIT 1");
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch();

                $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
                $stmt->execute([$invoiceId]);
                $items = $stmt->fetchAll();
            } else {
                // Créer une nouvelle facture avec numéro continu FA-AAAA-0001
                $datePart = gmdate('Y');
                $stmt = $db->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
                $stmt->execute(['FA-' . $datePart . '-%']);
                $last = $stmt->fetch();
                $seq = 1;
                if ($last !== false) {
                    $parts = explode('-', $last['invoice_number']);
                    $seq = (int)($parts[2] ?? 0) + 1;
                }
                $invoiceNumber = 'FA-' . $datePart . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

                $invoiceDate = new DateTime('now', new DateTimeZone('UTC'));
                $invoiceDateStr = $invoiceDate->format('Y-m-d H:i:s');
                $dueDate = (clone $invoiceDate)->modify('+30 days')->format('Y-m-d H:i:s');

                $subtotalHt = (float)$order['subtotal'];
                $shippingHt = (float)$order['shipping_amount'];
                $vatAmount = (float)$order['vat_amount'];
                $discountHt = (float)$order['discount_amount'];
                $totalTtc = (float)$order['total'];

                $db->beginTransaction();
                try {
                    $db->prepare(
                        "INSERT INTO invoices (invoice_number, order_id, customer_id, invoice_date, due_date, delivery_date,
                         subtotal_ht, vat_amount, vat_rate, vat_label, shipping_ht, discount_ht, total_ttc,
                         currency_code, status, notes, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'émise', ?, NOW())"
                    )->execute([
                        $invoiceNumber,
                        $orderId,
                        $order['customer_id'],
                        $invoiceDateStr,
                        $dueDate,
                        $invoiceDateStr, // date de livraison = émission par défaut
                        $subtotalHt,
                        $vatAmount,
                        null, // vat_rate (général)
                        'TVA selon pays de destination',
                        $shippingHt,
                        $discountHt,
                        $totalTtc,
                        $order['currency_code'],
                        'Facture générée automatiquement depuis la commande ' . $order['order_number'],
                    ]);
                    $invoiceId = (int)$db->lastInsertId();

                    // Lignes figées
                    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
                    $stmt->execute([$orderId]);
                    $orderItems = $stmt->fetchAll();

                    $stmtItem = $db->prepare(
                        "INSERT INTO invoice_items (invoice_id, reference, name, quantity, unit_price_ht, discount, total_ht, vat_rate, total_ttc)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    foreach ($orderItems as $it) {
                        $stmtItem->execute([
                            $invoiceId,
                            $it['reference'],
                            $it['name'],
                            (int)$it['quantity'],
                            (float)$it['unit_price'],
                            (float)$it['discount'],
                            (float)$it['total'],
                            null,
                            (float)$it['total'],
                        ]);
                    }

                    $db->commit();

                    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1");
                    $stmt->execute([$invoiceId]);
                    $invoice = $stmt->fetch();

                    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
                    $stmt->execute([$invoiceId]);
                    $items = $stmt->fetchAll();
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
        } else {
            faError(400, 'Commande ou facture invalide.');
        }

        // Infos client pour la facture
        $clientName = $order['firstname'] . ' ' . $order['lastname'];
        $clientCompany = $order['company'] ?? null;
        $clientVat = $order['vat_number'] ?? null;
        $clientAddress = [
            $order['address'],
            $order['postcode'] . ' ' . $order['city'],
            $order['country_name'],
        ];

        // Infos entreprise depuis settings
        $companyName = $settings['site_name'] ?? 'EuroImport Export';
        $companyAddress = $settings['address'] ?? '';
        $companyPhone = $settings['public_phone'] ?? '';
        $companyEmail = $settings['contact_email'] ?? '';
        $companySiret = $settings['siret'] ?? '';
        $companyTva = $settings['tva_intracommunautaire'] ?? '';
        $companyEori = $settings['eori'] ?? '';
        $companyIban = $settings['iban'] ?? '';
        $companyBic = $settings['bic'] ?? '';
        $companyBank = $settings['bank_name'] ?? '';

        $invoiceNumber = $invoice['invoice_number'] ?? 'FA-——';
        $invoiceDate = $invoice['invoice_date'] ?? date('Y-m-d H:i:s');
        $dueDate = $invoice['due_date'] ?? null;
        $currency = $invoice['currency_code'] ?? 'EUR';
        $symbol = 'EUR';
        $curr = $db->prepare("SELECT symbol FROM currencies WHERE code = ? LIMIT 1");
        $curr->execute([$currency]);
        $cRow = $curr->fetch();
        if ($cRow !== false) { $symbol = $cRow['symbol']; }

        $subtotalHt = (float)$invoice['subtotal_ht'];
        $vatAmount = (float)$invoice['vat_amount'];
        $shippingHt = (float)$invoice['shipping_ht'];
        $discountHt = (float)$invoice['discount_ht'];
        $totalTtc = (float)$invoice['total_ttc'];
        $vatLabel = $invoice['vat_label'] ?? 'TVA';

        // Générer le PDF A4 (générateur interne, sans dépendance Composer).
        $pdf = new InvoicePdf();
        $m = $pdfMargin = 38.0;
        $pageW = 595.28;
        $contentW = $pageW - $m * 2;
        $orange = [1.0, 0.40, 0.0];
        $black = [0.10, 0.10, 0.10];
        $gray = [0.40, 0.40, 0.40];

        // En-tête
        $pdf->text($companyName, $m, 20, true, $orange);
        $y = $m + 24;
        foreach (preg_split('/\R/', $companyAddress ?: '') as $line) {
            $line = trim((string)$line);
            if ($line === '') { continue; }
            $pdf->text($line, $m, 9, false, $gray, $y);
            $y += 11;
        }
        if ($companyPhone !== '') { $pdf->text('Tél : ' . $companyPhone, $m, 9, false, $gray, $y); $y += 11; }
        if ($companyEmail !== '') { $pdf->text('Email : ' . $companyEmail, $m, 9, false, $gray, $y); $y += 11; }

        $rightX = 340;
        $pdf->text('FACTURE', $rightX, 18, true, $black, $m);
        $pdf->text($invoiceNumber, $rightX, 12, true, $orange, $m + 22);
        $metaY = $m + 40;
        $metaLines = [
            "Date d'émission : " . date('d/m/Y', strtotime($invoiceDate)),
        ];
        if ($dueDate) { $metaLines[] = "Date d'échéance : " . date('d/m/Y', strtotime($dueDate)); }
        $metaLines[] = 'Date de livraison : ' . date('d/m/Y', strtotime($invoice['delivery_date'] ?? $invoiceDate));
        $metaLines[] = 'Commande : ' . $order['order_number'];
        $metaLines[] = 'Devise : ' . $currency;
        foreach ($metaLines as $ml) {
            $pdf->text($ml, $rightX, 9, false, $gray, $metaY);
            $metaY += 12;
        }

        $headerBottom = max($y, $metaY) + 10;
        $pdf->line($m, $headerBottom, $pageW - $m, $headerBottom, [0.20, 0.20, 0.20], 1.2);

        // Client
        $pdf->text('FACTURÉ À', $m, 10, true, $orange, $headerBottom + 18);
        $cy = $headerBottom + 32;
        $clientLines = [$clientName];
        if ($clientCompany) { $clientLines[] = $clientCompany; }
        if ($clientVat) { $clientLines[] = 'TVA intra : ' . $clientVat; }
        $clientLines[] = (string)$order['address'];
        $clientLines[] = trim((string)$order['postcode'] . ' ' . (string)$order['city']);
        $clientLines[] = (string)$order['country_name'];
        foreach ($clientLines as $cl) {
            $cl = trim((string)$cl);
            if ($cl === '') { continue; }
            $pdf->text($cl, $m, 9, false, $black, $cy);
            $cy += 12;
        }

        // Tableau articles
        $tableTop = $cy + 14;
        $pdf->text('DÉTAIL DES ARTICLES', $m, 10, true, $orange, $tableTop);
        $tableTop += 14;
        $cols = [
            ['label' => 'Réf.', 'x' => $m, 'w' => 72, 'align' => 'left'],
            ['label' => 'Désignation', 'x' => $m + 72, 'w' => 178, 'align' => 'left'],
            ['label' => 'Qté', 'x' => $m + 250, 'w' => 35, 'align' => 'right'],
            ['label' => 'PU HT', 'x' => $m + 285, 'w' => 75, 'align' => 'right'],
            ['label' => 'Remise', 'x' => $m + 360, 'w' => 65, 'align' => 'right'],
            ['label' => 'Total HT', 'x' => $m + 425, 'w' => 94, 'align' => 'right'],
        ];

        $drawHeader = function (float $yTop) use ($pdf, $cols, $contentW) {
            $pdf->rect($m = $cols[0]['x'], $yTop, $contentW, 18, [0.96, 0.96, 0.96]);
            foreach ($cols as $col) {
                $tx = $col['align'] === 'right' ? $col['x'] + $col['w'] - 4 : $col['x'] + 4;
                $text = $col['label'];
                if ($col['align'] === 'right') {
                    $tx -= $pdf->measure($text, 8, true);
                }
                $pdf->text($text, $tx, 8, true, [0.20, 0.20, 0.20], $yTop + 12);
            }
            $pdf->line($cols[0]['x'], $yTop + 18, $cols[0]['x'] + $contentW, $yTop + 18, [0.85, 0.85, 0.85], 0.5);
        };

        $drawHeader($tableTop);
        $rowY = $tableTop + 18;

        foreach ($items as $it) {
            $name = (string)($it['name'] ?? '');
            $nameLines = $pdf->wrap($name, $cols[1]['w'] - 8, 8);
            $lineCount = max(1, count($nameLines));
            $rowH = max(18, $lineCount * 10 + 8);

            if ($rowY + $rowH > 780) {
                $pdf->newPage();
                $rowY = 48;
                $drawHeader($rowY);
                $rowY += 18;
            }

            $pdf->rect($cols[0]['x'], $rowY, $contentW, $rowH, [1, 1, 1]);
            $pdf->line($cols[0]['x'], $rowY + $rowH, $cols[0]['x'] + $contentW, $rowY + $rowH, [0.90, 0.90, 0.90], 0.4);

            $values = [
                (string)($it['reference'] ?? ''),
                $nameLines,
                (string)(int)($it['quantity'] ?? 0),
                number_format((float)($it['unit_price_ht'] ?? 0), 2, ',', ' ') . ' ' . $symbol,
                number_format((float)($it['discount'] ?? 0), 2, ',', ' ') . ' ' . $symbol,
                number_format((float)($it['total_ht'] ?? 0), 2, ',', ' ') . ' ' . $symbol,
            ];

            foreach ($cols as $i => $col) {
                $val = $values[$i];
                if (is_array($val)) {
                    $ly = $rowY + 11;
                    foreach ($val as $vl) {
                        $pdf->text($vl, $col['x'] + 4, 8, false, $black, $ly);
                        $ly += 10;
                    }
                } else {
                    $tx = $col['x'] + 4;
                    if ($col['align'] === 'right') {
                        $tx = $col['x'] + $col['w'] - 4 - $pdf->measure($val, 8, false);
                    }
                    $pdf->text($val, $tx, 8, false, $black, $rowY + 11);
                }
            }

            $rowY += $rowH;
        }

        // Totaux
        $totalsY = $rowY + 18;
        if ($totalsY + 110 > 780) {
            $pdf->newPage();
            $totalsY = 60;
        }
        $totX = 340;
        $totW = 217;
        $totals = [
            ['Sous-total HT', number_format($subtotalHt, 2, ',', ' ') . ' ' . $symbol, false],
        ];
        if ($discountHt > 0) {
            $totals[] = ['Remise', '- ' . number_format($discountHt, 2, ',', ' ') . ' ' . $symbol, false];
        }
        $totals[] = ['Frais de livraison HT', number_format($shippingHt, 2, ',', ' ') . ' ' . $symbol, false];
        $totals[] = [$vatLabel, number_format($vatAmount, 2, ',', ' ') . ' ' . $symbol, false];
        $totals[] = ['TOTAL TTC', number_format($totalTtc, 2, ',', ' ') . ' ' . $symbol, true];

        foreach ($totals as $t) {
            [$label, $value, $strong] = $t;
            $bg = $strong ? [1.0, 0.95, 0.90] : [1, 1, 1];
            $pdf->rect($totX, $totalsY, $totW, 18, $bg);
            $pdf->text($label, $totX + 6, $strong ? 9.5 : 9, $strong, $black, $totalsY + 12);
            $vx = $totX + $totW - 6 - $pdf->measure($value, $strong ? 9.5 : 9, $strong);
            $pdf->text($value, $vx, $strong ? 9.5 : 9, $strong, $strong ? $orange : $black, $totalsY + 12);
            $pdf->line($totX, $totalsY + 18, $totX + $totW, $totalsY + 18, [0.88, 0.88, 0.88], 0.4);
            $totalsY += 18;
        }

        // Coordonnées bancaires
        $bankY = $totalsY + 20;
        if ($bankY + 80 > 780) { $pdf->newPage(); $bankY = 60; }
        $pdf->rect($m, $bankY, $contentW, 62, [0.98, 0.98, 0.98]);
        $pdf->text('Coordonnées bancaires pour virement', $m + 8, 9.5, true, $black, $bankY + 14);
        $pdf->text('Banque : ' . $companyBank, $m + 8, 8.5, false, $gray, $bankY + 28);
        $pdf->text('IBAN : ' . $companyIban, $m + 8, 8.5, false, $gray, $bankY + 40);
        $pdf->text('BIC : ' . $companyBic, $m + 8, 8.5, false, $gray, $bankY + 52);
        $pdf->text('Référence virement : ' . $invoiceNumber, $m + 260, 8.5, false, $gray, $bankY + 40);

        // Mentions légales
        $legalY = $bankY + 76;
        if ($legalY + 90 > 780) { $pdf->newPage(); $legalY = 60; }
        $pdf->text('Mentions légales', $m, 8.5, true, $black, $legalY);
        $legalY += 12;
        $legal = $companyName . ' – ' . preg_replace('/\s+/', ' ', $companyAddress);
        if ($companySiret !== '') { $legal .= ' | SIRET : ' . $companySiret; }
        if ($companyTva !== '') { $legal .= ' | TVA intracommunautaire : ' . $companyTva; }
        if ($companyEori !== '') { $legal .= ' | EORI : ' . $companyEori; }
        $legal .= '. Conditions de paiement : virement bancaire à réception de facture. Aucun paiement en ligne sur le site.';
        $legal .= ' Les droits de douane, taxes d\'importation et TVA locale éventuels sont à la charge de l\'acheteur hors Union européenne.';
        $legal .= ' Cette facture est émise en français. Toute traduction automatique n\'a pas de valeur juridique.';
        $pdf->paragraph($legal, $m, $contentW, 7.5, [0.45, 0.45, 0.45], false, 10);

        $pdfContent = $pdf->output();

        // Sauvegarder le PDF sur le serveur
        $pdfDir = __DIR__ . '/../../uploads/pdfs/';
        if (!is_dir($pdfDir)) { @mkdir($pdfDir, 0755, true); }
        $safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $invoiceNumber);
        $pdfFile = $pdfDir . 'invoice_' . $safeNumber . '.pdf';
        file_put_contents($pdfFile, $pdfContent);

        // Mettre à jour le chemin PDF
        $db->prepare("UPDATE invoices SET pdf_path = ? WHERE id = ?")->execute(['uploads/pdfs/invoice_' . $safeNumber . '.pdf', $invoiceId]);

        $filename = 'facture_' . $safeNumber . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdfContent;
        exit;
    }

    faError(400, 'Action inconnue.');

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
