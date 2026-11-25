<?php
declare(strict_types=1);

/**
 * API : validation et enregistrement d'une commande.
 * - Vérifie le CSRF et les données du formulaire
 * - Crée / met à jour le client (compte invité si non connecté)
 * - Enregistre la commande (statut "nouvelle") avec numéro unique
 * - Enregistre les lignes de commande (figées)
 * - Retourne JSON avec l'URL WhatsApp et le numéro de commande
 */

require_once __DIR__ . '/../includes/config_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function apiError(int $httpCode, string $message): never
{
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = db();
    $settings = loadSettings($db);

    // --- CSRF ---
    $csrf = $_POST['csrf_token'] ?? '';
    if (!csrfCheck($csrf)) {
        apiError(403, 'Jeton CSRF invalide.');
    }

    // --- Panier ---
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart) || $cart === []) {
        apiError(400, 'Votre panier est vide.');
    }

    // --- Récupération et validation des champs ---
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $vatNumber = trim($_POST['vat_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postcode = trim($_POST['postcode'] ?? '');
    $countryIso = trim($_POST['country'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $cgv = isset($_POST['cgv']) ? '1' : '0';

    if ($firstname === '' || $lastname === '') {
        apiError(400, 'Prénom et nom sont obligatoires.');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiError(400, 'Adresse email invalide.');
    }
    if ($phone === '') {
        apiError(400, 'Le numéro de téléphone est obligatoire.');
    }
    // Validation E.164 simple : commence par +, puis 7 à 15 chiffres
    if (!preg_match('/^\+[0-9]{7,15}$/', preg_replace('/\s/', '', $phone))) {
        apiError(400, 'Le numéro de téléphone doit être au format international (ex. +33 6 12 34 56 78).');
    }
    if ($address === '' || $city === '' || $postcode === '') {
        apiError(400, 'Adresse, ville et code postal sont obligatoires.');
    }
    if ($countryIso === '') {
        apiError(400, 'Le pays de livraison est obligatoire.');
    }
    if ($cgv !== '1') {
        apiError(400, 'Vous devez accepter les conditions générales de vente.');
    }

    $country = getCountry($db, $countryIso);
    if ($country === null) {
        apiError(400, 'Pays de livraison invalide.');
    }

    // --- Calcul des montants (figés au moment de la commande) ---
    $subtotal = 0.0;
    $totalWeight = 0.0;
    $orderItemsData = [];

    foreach ($cart as $reference => $item) {
        $stmt = $db->prepare("SELECT id, reference, name, price, weight, stock FROM products WHERE reference = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$reference]);
        $product = $stmt->fetch();
        if ($product === false) {
            continue;
        }
        $qty = (int)$item['qty'];
        $unitPrice = (float)$product['price'];
        $lineTotal = $unitPrice * $qty;
        $subtotal += $lineTotal;
        $totalWeight += (float)$product['weight'] * $qty;
        $orderItemsData[] = [
            'product_id' => (int)$product['id'],
            'reference' => $product['reference'],
            'name' => $product['name'],
            'sku' => $product['sku'] ?? null,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'unit_price_currency' => 'EUR',
            'discount' => 0.0,
            'total' => $lineTotal,
            'weight' => (float)$product['weight'],
        ];
    }

    if ($orderItemsData === []) {
        apiError(400, 'Le panier ne contient aucun article valide.');
    }

    $shippingRate = getShippingRateForCountry($db, $countryIso);
    $shippingAmount = $shippingRate !== null ? (float)$shippingRate['price'] : 0.0;
    $shippingCurrency = $shippingRate !== null ? ($shippingRate['currency_code'] ?? 'EUR') : 'EUR';

    $vatEnabledFlag = vatEnabled($db);
    $vatRate = 0.0;
    $vatAmount = 0.0;
    $vatLabel = 'TVA non applicable';
    if ($vatEnabledFlag) {
        $vatRate = getVatRate($db, $countryIso);
        if ((int)$country['is_eu'] === 1) {
            $vatAmount = round($subtotal * ($vatRate / 100), 2);
            $vatLabel = 'TVA ' . number_format((float)$vatRate, 2, ',', ' ') . '%';
        } else {
            $vatLabel = 'Exportation hors UE — TVA non applicable';
        }
    }

    $currency = detectCurrency($db);
    $exchangeRate = (float)$currency['exchange_rate'];
    $currencyCode = $currency['code'];

    $totalBase = $subtotal + $shippingAmount + $vatAmount;
    $totalConverted = round(convertAmount($totalBase, ['code' => 'EUR', 'exchange_rate' => 1.000000], $currency), 2);

    // --- Client : connecté ou invité ---
    $customerId = null;
    $isLoggedIn = !empty($_SESSION['customer_id']);

    if ($isLoggedIn) {
        $customerId = (int)$_SESSION['customer_id'];
        // Mise à jour optionnelle des infos client
        $db->prepare(
            "UPDATE customers SET firstname=?, lastname=?, phone=?, address=?, city=?, postcode=?, country_id=?, company=?, vat_number=? WHERE id=?"
        )->execute([
            $firstname, $lastname, $phone, $address, $city, $postcode,
            (int)$country['id'], $company ?: null, $vatNumber ?: null, $customerId
        ]);
    } else {
        // Vérifier si un client existe déjà avec cet email
        $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        if ($existing !== false) {
            $customerId = (int)$existing['id'];
            $db->prepare(
                "UPDATE customers SET firstname=?, lastname=?, phone=?, address=?, city=?, postcode=?, country_id=?, company=?, vat_number=? WHERE id=?"
            )->execute([
                $firstname, $lastname, $phone, $address, $city, $postcode,
                (int)$country['id'], $company ?: null, $vatNumber ?: null, $customerId
            ]);
        } else {
            // Créer un compte invité (sans mot de passe, activation ultérieure possible)
            $stmt = $db->prepare(
                "INSERT INTO customers (email, firstname, lastname, phone, address, city, postcode, country_id, company, vat_number, password_hash, is_active, email_verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, 0, NOW(), NOW())"
            );
            $stmt->execute([
                $email, $firstname, $lastname, $phone, $address, $city, $postcode,
                (int)$country['id'], $company ?: null, $vatNumber ?: null
            ]);
            $customerId = (int)$db->lastInsertId();
        }
    }

    // --- Génération du numéro de commande unique ---
    $datePart = gmdate('Ymd');
    $stmt = $db->prepare(
        "SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY order_number DESC LIMIT 1"
    );
    $stmt->execute(['CMD-' . $datePart . '-%']);
    $last = $stmt->fetch();
    $seq = 1;
    if ($last !== false) {
        $parts = explode('-', $last['order_number']);
        $seq = (int)($parts[2] ?? 0) + 1;
    }
    $orderNumber = 'CMD-' . $datePart . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);

    // --- Insertion de la commande ---
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO orders
                (order_number, customer_id, currency_code, exchange_rate, subtotal, shipping_amount, vat_amount, discount_amount, total,
                 status, payment_method, payment_status, customer_note, firstname, lastname, company, vat_number, phone, email,
                 address, city, postcode, country_id, country_name, ip_address, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, 0.0000, ?, 'nouvelle', 'hors_ligne', 'en_attente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $orderNumber,
            $customerId,
            $currencyCode,
            $exchangeRate,
            (float)$subtotal,
            $shippingAmount,
            $vatAmount,
            $totalConverted,
            $note,
            $firstname,
            $lastname,
            $company ?: null,
            $vatNumber ?: null,
            $phone,
            $email,
            $address,
            $city,
            $postcode,
            (int)$country['id'],
            $country['name'],
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $orderId = (int)$db->lastInsertId();

        // Lignes de commande
        $stmtItem = $db->prepare(
            "INSERT INTO order_items
                (order_id, product_id, reference, name, sku, quantity, unit_price, unit_price_currency, discount, total, weight)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($orderItemsData as $it) {
            $stmtItem->execute([
                $orderId,
                $it['product_id'],
                $it['reference'],
                $it['name'],
                $it['sku'],
                $it['quantity'],
                $it['unit_price'],
                $it['unit_price_currency'],
                $it['discount'],
                $it['total'],
                $it['weight'],
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    // --- Vider le panier ---
    $_SESSION['cart'] = [];

    // --- Construire le message WhatsApp (TOUJOURS EN FRANÇAIS) ---
    $whatsappNumber = preg_replace('/\s/', '', $settings['whatsapp_order'] ?? '+33612345678');
    $whatsappNumber = ltrim($whatsappNumber, '+');

    $siteNameRaw = $settings['site_name'] ?? 'EuroImport Export';
    $lines = [];
    $lines[] = 'NOUVELLE COMMANDE - ' . $siteNameRaw;
    $lines[] = 'Numero de commande : ' . $orderNumber;
    $lines[] = 'Date : ' . gmdate('d/m/Y H:i') . ' UTC';
    $lines[] = '';
    $lines[] = 'INFORMATIONS CLIENT :';
    $lines[] = '- Nom : ' . $firstname . ' ' . $lastname;
    if ($company !== '') { $lines[] = '- Entreprise : ' . $company; }
    if ($vatNumber !== '') { $lines[] = '- N° TVA : ' . $vatNumber; }
    $lines[] = '- Email : ' . $email;
    $lines[] = '- Telephone : ' . $phone;
    $lines[] = '- Adresse : ' . $address;
    $lines[] = '- Code postal : ' . $postcode;
    $lines[] = '- Ville : ' . $city;
    $lines[] = '- Pays : ' . $country['name'] . ' (' . $countryIso . ')';
    if ($note !== '') { $lines[] = '- Note client : ' . $note; }
    $lines[] = '';
    $lines[] = 'DETAIL DES ARTICLES :';
    foreach ($orderItemsData as $it) {
        $lines[] = '- ' . $it['reference'] . ' | ' . $it['name'] . ' | Qte ' . $it['quantity'] . ' | ' . number_format((float)$it['unit_price'], 2, ',', ' ') . ' EUR | Total ' . number_format((float)$it['total'], 2, ',', ' ') . ' EUR';
    }
    $lines[] = '';
    $lines[] = 'TOTAUX :';
    $lines[] = '- Sous-total HT : ' . number_format((float)$subtotal, 2, ',', ' ') . ' EUR';
    $lines[] = '- Frais de livraison : ' . number_format($shippingAmount, 2, ',', ' ') . ' ' . $shippingCurrency;
    $lines[] = '- TVA : ' . number_format((float)$vatAmount, 2, ',', ' ') . ' EUR (' . $vatLabel . ')';
    $lines[] = '- TOTAL TTC : ' . number_format((float)$totalConverted, 2, ',', ' ') . ' ' . $currencyCode;
    $lines[] = '';
    $lines[] = 'Paiement hors ligne (virement ou autre moyen convenu).';
    $lines[] = 'Merci de confirmer la reception de cette commande par WhatsApp.';

    $message = implode("\n", $lines);

    $waUrl = 'https://wa.me/' . $whatsappNumber . '?text=' . rawurlencode($message);

    echo json_encode([
        'ok' => true,
        'order_number' => $orderNumber,
        'order_id' => $orderId,
        'total' => $totalConverted,
        'currency' => $currencyCode,
        'whatsapp_url' => $waUrl,
        'whatsapp_number' => $whatsappNumber,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
