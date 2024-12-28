<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['API_KEY'];
$folderId = $_ENV['FOLDER_ID'];

$page = isset($_POST['page']) ? htmlspecialchars($_POST['page']) : "1";
$value = isset($_POST['value']) ? htmlspecialchars($_POST['value']) : "10";
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $searchQuery = htmlspecialchars($_POST['query']);
    $site = htmlspecialchars($_POST['site']);
    $query = "site:" . urlencode($site) . " " . urlencode($searchQuery);

    $baseUrl = "https://yandex.ru/search/xml?";
    $params = [
        "folderid" => $folderId,
        "apikey" => $apiKey,
        "query" => $query,
        "lr" => "11316",
        "l10n" => "ru",
        "sortby" => "rlv",
        "filter" => "none",
        "groupby" => "attr=d.mode=deep.groups-on-page=$value.docs-in-group=3",
        "maxpassages" => "4",
        "page" => $page
    ];
    
    $url = $baseUrl . http_build_query($params);


    $response = file_get_contents($url);

    if ($response !== false) {
        $xml = simplexml_load_string($response);

        if ($xml !== false && isset($xml->response->results->grouping->group)) {
            foreach ($xml->response->results->grouping->group as $group) {
                $title = $group->doc->title;
                $link = (string)$group->doc->url;
                $modtime = $group->doc->modtime;

                $title = preg_replace('/<hlword>(.*?)<\/hlword>/', '$1', $title->asXML());
                $title = strip_tags($title);

                $modtimeObj = DateTime::createFromFormat('Ymd\THis', $modtime);
    
                if ($modtimeObj) {
                    $modtimeFormatted = $modtimeObj->format('Y-m-d H:i:s');
                } else {
                    $modtimeFormatted = "Ошибка: не удалось преобразовать дату.";
                }

                $results[] = [
                    'link' => $link,
                    'title' => $title,
                    'data' => $modtimeFormatted,
                ];
            }
        }
    }
}

if (isset($_POST['download_csv'])) {
    $fileName = "result.csv";
    $file = fopen($fileName, 'w');
    fputcsv($file, ["Link", "Title", "Data"]);
    foreach ($results as $result) {
        fputcsv($file, [$result['link'], $result['title'], $result['data']]);
    }
    fclose($file);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    readfile($fileName);
    unlink($fileName);
    exit;
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поисковая форма</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            background-color: #f4f4f9;
        }
        form, .results {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin-bottom: 20px;
        }
        label, table {
            display: block;
            margin: 10px 0 5px;
            color: #555;
        }
        input, select {
            height: 30px;
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            height: 40px;
            width: 100%;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #4cae4c;
        }
        table {
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #5cb85c;
            color: white;
        }
    </style>
</head>
<body>
<form method="POST">
    <label for="query">Запрос:</label>
    <input type="text" id="query" name="query" required value="<?= htmlspecialchars($_POST['query'] ?? ''); ?>">

    <label for="site">Сайт:</label>
    <input type="text" id="site" name="site" value="<?= htmlspecialchars($_POST['site'] ?? ''); ?>">

    <label for="page">Номер страницы (page):</label>
    <input type="number" id="page" name="page" value="<?= htmlspecialchars($_POST['page'] ?? '1'); ?>">
    
    <label for="value">Кол-во страниц: (1-100)</label>
    <input type="number" id="value" name="value" value="<?= htmlspecialchars($_POST['value'] ?? '10'); ?>">
 
    <button type="submit">Найти</button>
</form>

    <?php if (!empty($results)): ?>
        <div class="results">
            <table>
                <thead>
                    <tr>
                        <th>Link</th>
                        <th>Title</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <td><a href="<?= htmlspecialchars($result['link']); ?>" target="_blank">Ссылка</a></td>
                            <td><?= htmlspecialchars($result['title']); ?></td>
                            <td><?= htmlspecialchars($result['data']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="POST">
            <input type="hidden" name="query" value="<?= htmlspecialchars($_POST['query']); ?>">
            <input type="hidden" name="site" value="<?= htmlspecialchars($_POST['site']); ?>">
            <button type="submit" name="download_csv">Скачать CSV</button>
        </form>
        
    <?php endif; ?>
</body>
</html>
