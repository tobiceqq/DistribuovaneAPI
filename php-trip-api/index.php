<?php

header("Content-Type: application/json; charset=utf-8");

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Info o API
if ($method === "GET" && $path === "/") {
    echo json_encode([
        "service" => "Plánovač výletů API",
        "version" => "1.0",
        "description" => "API pro plánování výletů",
        "connected_api" => "Lokální atrakce API",
        "endpoints" => [
            "GET /",
            "GET /locality/date-range",
            "POST /plan-trip"
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Ziskani atrakci podle mesta
if ($method === "GET" && $path === "/locality/date-range") {

    $city = trim($_GET["city"] ?? "");

    if ($city === "") {
        http_response_code(400);
        echo json_encode([
            "error" => "Musíte zadat město."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Volani C# API
    $url = "http://localhost:5001/attractions/date?city="
        . urlencode($city);

    $context = stream_context_create([
        "http" => [
            "timeout" => 5,
            "ignore_errors" => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        http_response_code(502);
        echo json_encode([
            "error" => "C# API není dostupné."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        http_response_code(502);
        echo json_encode([
            "error" => "C# API vrátilo neplatnou odpověď."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "city" => $city,
        "attractions" => $data["attractions"] ?? []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Vytvoreni noveho vyletu
if ($method === "POST" && $path === "/plan-trip") {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Neplatná JSON data."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $city = trim($input["city"] ?? "");
    $dateFrom = $input["date_from"] ?? "";
    $dateTo = $input["date_to"] ?? "";
    $people = $input["people"] ?? 0;
    $attractions = $input["attractions"] ?? [];

    // Kontrola udaju
    if (
        $city === "" ||
        !is_string($dateFrom) ||
        !is_string($dateTo) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ||
        $dateTo < $dateFrom ||
        !is_int($people) ||
        $people < 1 ||
        !is_array($attractions)
    ) {
        http_response_code(400);
        echo json_encode([
            "error" => "Zkontrolujte město, termín a počet osob."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Nacteni atrakci z C# API
    $url = "http://localhost:5001/attractions/date?city="
        . urlencode($city);

    $context = stream_context_create([
        "http" => [
            "timeout" => 5,
            "ignore_errors" => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        http_response_code(502);
        echo json_encode([
            "error" => "Nepodařilo se kontaktovat API atrakcí."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($response, true);

    if (!is_array($data) ||
        !isset($data["attractions"]) ||
        !is_array($data["attractions"])) {
        http_response_code(502);
        echo json_encode([
            "error" => "API atrakcí vrátilo neplatná data."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kontrola vybranych atrakci
    $availableIds = array_column($data["attractions"], "id");

    foreach ($attractions as $attractionId) {
        if (!is_string($attractionId) ||
            !in_array($attractionId, $availableIds, true)) {
            http_response_code(400);
            echo json_encode([
                "error" => "Neplatná atrakce.",
                "attraction_id" => $attractionId
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Nacteni ulozenych vyletu
    $file = __DIR__ . "/trips.json";

    $trips = file_exists($file)
        ? json_decode(file_get_contents($file), true)
        : [];

    if (!is_array($trips)) {
        $trips = [];
    }

    // Novy vylet
    $trip = [
        "id" => uniqid("TRIP-"),
        "city" => $city,
        "date_from" => $dateFrom,
        "date_to" => $dateTo,
        "people" => $people,
        "attractions" => $attractions,
        "status" => "created"
    ];

    // Ulozeni do JSON
    $trips[] = $trip;

    if (file_put_contents(
        $file,
        json_encode($trips, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    ) === false) {
        http_response_code(500);
        echo json_encode([
            "error" => "Výlet se nepodařilo uložit."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(201);

    echo json_encode([
        "message" => "Výlet byl úspěšně vytvořen.",
        "trip" => $trip
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Neznamy endpoint
http_response_code(404);

echo json_encode([
    "error" => "Endpoint neexistuje."
], JSON_UNESCAPED_UNICODE);
