<?php
session_start();

// Подключение библиотеки TCPDF для создания PDF-документов
require_once('tcpdf/tcpdf.php');

/**
 * Генерирует PDF-отчет на основе данных оценки
 * @param array $data Массив с данными (баллы, проценты, рекомендации и т.д.)
 * @return string Путь к созданному PDF-файлу
 * @throws Exception В случае ошибок при создании директории или записи файла
 */
function generatePDF($data) {
    // Определение директории для хранения отчетов
    $baseDir = '/var/www/u3064951/data/www/vitvin.online/reports/';
    
    // Проверка и создание директории, если она не существует
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0775, true)) {
            throw new Exception('Не удалось создать директорию reports/');
        }
    }
    
    // Проверка прав на запись в директорию
    if (!is_writable($baseDir)) {
        throw new Exception('Директория reports/ недоступна для записи');
    }
    
    // Генерация уникального имени файла с временной меткой
    $filename = $baseDir . 'report_' . time() . '.pdf';
    
    // Инициализация объекта TCPDF
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Московская Биржа');
    $pdf->SetTitle('Отчет о готовности к IPO');
    $pdf->AddPage();

    // Добавление логотипа в левый верхний угол
    $pdf->Image('moex.png', 10, 10, 50, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

    // Установка шрифта и смещение вниз для контента
    $pdf->SetFont('dejavusans', '', 12);
    $pdf->SetY(40);
    $pdf->Write(0, "Отчет о готовности к IPO\n\n");

    // Добавление результатов по категориям
    $pdf->Write(0, "Стратегическая готовность: {$data['strategicScore']} из {$data['maxStrategic']} (" . number_format($data['strategicPercent'], 2) . "%)\n\n");
    $pdf->Write(0, "Размер и рыночная позиция: {$data['marketScore']} из {$data['maxMarket']} (" . number_format($data['marketPercent'], 2) . "%)\n\n");
    $pdf->Write(0, "Финансовая готовность: {$data['financialScore']} из {$data['maxFinancial']} (" . number_format($data['financialPercent'], 2) . "%)\n\n");
    $pdf->Write(0, "Организационная готовность: {$data['orgScore']} из {$data['maxOrg']} (" . number_format($data['orgPercent'], 2) . "%)\n\n");
    $pdf->Write(0, "Юридическая готовность: {$data['legalScore']} из {$data['maxLegal']} (" . number_format($data['legalPercent'], 2) . "%)\n\n");
    $pdf->Write(0, "Готовность к коммуникациям: {$data['commScore']} из {$data['maxComm']} (" . number_format($data['commPercent'], 2) . "%)\n\n");

    // Добавление диаграммы в виде текстовой таблицы
    $pdf->Write(0, "Диаграмма результатов:\n");
    $table = "<table border='1' cellpadding='5'>";
    $table .= "<tr><th>Категория</th><th>Процент готовности</th></tr>";
    $table .= "<tr><td>Стратегическая</td><td>" . number_format($data['strategicPercent'], 2) . "%</td></tr>";
    $table .= "<tr><td>Рыночная</td><td>" . number_format($data['marketPercent'], 2) . "%</td></tr>";
    $table .= "<tr><td>Финансовая</td><td>" . number_format($data['financialPercent'], 2) . "%</td></tr>";
    $table .= "<tr><td>Организационная</td><td>" . number_format($data['orgPercent'], 2) . "%</td></tr>";
    $table .= "<tr><td>Юридическая</td><td>" . number_format($data['legalPercent'], 2) . "%</td></tr>";
    $table .= "<tr><td>Коммуникационная</td><td>" . number_format($data['commPercent'], 2) . "%</td></tr>";
    $table .= "</table>";
    $pdf->writeHTML($table, true, false, true, false, '');
    $pdf->Ln(10);

    // Общий балл и уровень готовности
    $pdf->Write(0, "Общий балл: {$data['totalScore']} из {$data['maxTotal']} (" . number_format($data['totalPercent'], 2) . "%)\n\n");
    $pdf->Write(0, "Уровень готовности: {$data['readinessLevel']}\n\n");

    // Добавление общего анализа
    $pdf->Write(0, "Общий анализ:\n");
    $pdf->Write(0, $data['generalAssessment'] . "\n\n");

    // Добавление рекомендаций
    $pdf->Write(0, "Рекомендации:\n");
    foreach ($data['recommendations'] as $rec) {
        $pdf->Write(0, implode("\n", $rec['paragraphs']) . "\n\n");
        $pdf->Write(0, "Пошаговый план:\n");
        $plan = implode("\n", array_map(fn($item, $i) => ($i + 1) . ". $item", $rec['plan'], array_keys($rec['plan'])));
        $pdf->Write(0, $plan . "\n\n");
    }

    // Сохранение PDF-файла
    try {
        $pdf->Output($filename, 'F');
    } catch (Exception $e) {
        error_log("TCPDF error: " . $e->getMessage(), 3, '/tmp/tcpdf_errors.log');
        throw new Exception('Ошибка при сохранении PDF: ' . $e->getMessage());
    }
    return $filename;
}

/**
 * Обрабатывает данные формы, вычисляет результаты и сохраняет их
 * @param array $postData Данные, отправленные формой
 */
function processForm($postData) {
    global $_SESSION;

    // Сохранение ответов на вопросы (q1–q25)
    for ($i = 1; $i <= 25; $i++) {
        $_SESSION["q$i"] = isset($postData["q$i"]) ? (int)$postData["q$i"] : null;
    }

    // Сохранение контактной информации с экранированием
    $_SESSION['name'] = htmlspecialchars($postData['name'] ?? '');
    $_SESSION['company'] = htmlspecialchars($postData['company'] ?? '');
    $_SESSION['position'] = htmlspecialchars($postData['position'] ?? '');
    $_SESSION['phone'] = htmlspecialchars($postData['phone'] ?? '');
    $_SESSION['email'] = htmlspecialchars($postData['email'] ?? '');
    $_SESSION['consultation'] = isset($postData['consultation']) ? true : false;

    // Проверка, что все вопросы и контактные данные заполнены
    $allQuestionsFilled = true;
    for ($i = 1; $i <= 25; $i++) {
        if (!isset($postData["q$i"])) {
            $allQuestionsFilled = false;
            break;
        }
    }
    $allContactFilled = !empty($postData['name']) && !empty($postData['company']) && 
                        !empty($postData['position']) && !empty($postData['phone']) && 
                        !empty($postData['email']);

    // Если данные неполные, сохранение ошибки
    if (!$allQuestionsFilled || !$allContactFilled) {
        $_SESSION['result'] = ['error' => 'Пожалуйста, заполните все вопросы и контактные данные!'];
        return;
    }

    // Расчет баллов по категориям
    $strategicScore = $_SESSION['q1'] + $_SESSION['q2'] + $_SESSION['q3'] + $_SESSION['q4'];
    $marketScore = $_SESSION['q5'] + $_SESSION['q6'] + $_SESSION['q7'] + $_SESSION['q8'] + $_SESSION['q9'];
    $financialScore = $_SESSION['q10'] + $_SESSION['q11'] + $_SESSION['q12'] + $_SESSION['q13'] + $_SESSION['q14'];
    $orgScore = $_SESSION['q15'] + $_SESSION['q16'] + $_SESSION['q17'] + $_SESSION['q18'] + $_SESSION['q19'];
    $legalScore = $_SESSION['q20'] + $_SESSION['q21'];
    $commScore = $_SESSION['q22'] + $_SESSION['q23'] + $_SESSION['q24'] + $_SESSION['q25'];

    // Определение максимальных баллов
    $maxStrategic = 83;
    $maxMarket = 130;
    $maxFinancial = 140;
    $maxOrg = 90;
    $maxLegal = 60;
    $maxComm = 90;

    // Вычисление процентов готовности
    $strategicPercent = ($strategicScore / $maxStrategic) * 100;
    $marketPercent = ($marketScore / $maxMarket) * 100;
    $financialPercent = ($financialScore / $maxFinancial) * 100;
    $orgPercent = ($orgScore / $maxOrg) * 100;
    $legalPercent = ($legalScore / $maxLegal) * 100;
    $commPercent = ($commScore / $maxComm) * 100;

    // Общий балл и процент
    $totalScore = $strategicScore + $marketScore + $financialScore + $orgScore + $legalScore + $commScore;
    $maxTotal = $maxStrategic + $maxMarket + $maxFinancial + $maxOrg + $maxLegal + $maxComm;
    $totalPercent = ($totalScore / $maxTotal) * 100;

    // Определение уровня готовности и текста анализа
    if ($totalPercent >= 80) {
        $readinessLevel = 'Высокий уровень готовности';
        $generalAssessment = 'Ваша компания демонстрирует высокий уровень готовности к IPO. Вы обладаете сильной стратегией, финансовой устойчивостью и организационной структурой, что делает вас привлекательным кандидатом для публичного размещения.';
        $defaultRecommendation = [
            'paragraphs' => [
                'Ваша компания находится на высоком уровне готовности к IPO. Продолжайте поддерживать текущие стандарты прозрачности и управления.',
                'Рассмотрите привлечение инвестиционных банков для финальной подготовки. Оптимизируйте процессы взаимодействия с инвесторами, чтобы укрепить доверие на рынке.',
                'Регулярно обновляйте финансовую модель, учитывая рыночные тренды. Убедитесь, что все процессы соответствуют требованиям регуляторов.'
            ],
            'plan' => [
                'Провести финальный аудит отчетности (1 месяц).',
                'Подготовить презентацию для инвесторов (2–3 недели).',
                'Провести роуд-шоу для привлечения интереса (1–2 месяца).',
                'Оптимизировать IR-раздел сайта (1 месяц).'
            ]
        ];
    } elseif ($totalPercent >= 60) {
        $readinessLevel = 'Средний уровень готовности';
        $generalAssessment = 'Ваша компания находится на среднем уровне готовности к IPO. Некоторые аспекты требуют доработки, но у вас есть хорошая основа для дальнейшего прогресса.';
        $defaultRecommendation = [
            'paragraphs' => [
                'Ваша компания имеет потенциал для успешного IPO, но требует доработки в отдельных областях. Сосредоточьтесь на устранении слабых сторон, таких как финансовая прозрачность или коммуникационная стратегия.',
                'Разработайте детальный план действий с привлечением профессиональных консультантов. Проведите внутренний аудит процессов для выявления скрытых рисков.',
                'Регулярно оценивайте прогресс подготовки. Это позволит повысить привлекательность для инвесторов.'
            ],
            'plan' => [
                'Нанять консультанта по IPO (1 месяц).',
                'Провести внутренний аудит процессов (2–3 месяца).',
                'Разработать коммуникационную стратегию (1–2 месяца).',
                'Начать подготовку МСФО-отчетности (3–6 месяцев).'
            ]
        ];
    } elseif ($totalPercent >= 40) {
        $readinessLevel = 'Низкий уровень готовности';
        $generalAssessment = 'Уровень готовности вашей компании к IPO оценивается как низкий. Необходима работа над несколькими ключевыми областями для достижения приемлемого уровня.';
        $defaultRecommendation = [
            'paragraphs' => [
                'Для подготовки к IPO вашей компании требуется значительная работа. Начните с разработки четкой стратегии и финансовой отчетности по стандартам МСФО.',
                'Привлеките консультантов для создания дорожной карты подготовки. Укрепите организационную структуру, внедрив внутренний аудит и управление рисками.',
                'Постепенно выстраивайте коммуникации с потенциальными инвесторами. Это создаст фундамент для успешного размещения.'
            ],
            'plan' => [
                'Сформировать рабочую группу по IPO (1 месяц).',
                'Разработать базовую стратегию (2–3 месяца).',
                'Начать переход на МСФО (3–6 месяцев).',
                'Внедрить внутренний аудит (2–4 месяца).'
            ]
        ];
    } else {
        $readinessLevel = 'Критически низкий уровень готовности';
        $generalAssessment = 'На данный момент ваша компания демонстрирует критически низкий уровень готовности. Требуется комплексная работа по всем направлениям.';
        $defaultRecommendation = [
            'paragraphs' => [
                'Ваша компания пока не готова к IPO и требует комплексной подготовки. Начните с создания базовых элементов: стратегии, финансовой модели и юридической консолидации активов.',
                'Привлеките профессиональных консультантов для формирования плана действий. Внедрите базовые процессы управления рисками и отчетности.',
                'Постепенно выстраивайте репутацию на рынке через PR-активности. Это заложит основу для дальнейшего прогресса.'
            ],
            'plan' => [
                'Нанять консультанта по стратегии (1 месяц).',
                'Разработать базовую финансовую модель (2–3 месяца).',
                'Начать консолидацию активов (3–6 месяцев).',
                'Запустить PR-кампанию (2–4 месяца).'
            ]
        ];
    }

    // Формирование рекомендаций для слабых категорий
    $scores = [
        ['name' => 'Стратегическая готовность', 'percent' => $strategicPercent],
        ['name' => 'Размер и рыночная позиция', 'percent' => $marketPercent],
        ['name' => 'Финансовая готовность', 'percent' => $financialPercent],
        ['name' => 'Организационная готовность', 'percent' => $orgPercent],
        ['name' => 'Юридическая готовность', 'percent' => $legalPercent],
        ['name' => 'Готовность к коммуникациям', 'percent' => $commPercent]
    ];
    usort($scores, fn($a, $b) => $a['percent'] <=> $b['percent']);

    $recommendations = [];
    foreach (array_slice($scores, 0, 3) as $area) {
        if ($area['percent'] < 60) {
            if ($area['name'] === 'Стратегическая готовность') {
                $recommendations[] = [
                    'paragraphs' => [
                        'Ваша стратегическая готовность требует значительных улучшений. Разработайте четкую, документированную стратегию с долгосрочными целями и конкретными KPI, чтобы повысить управляемость бизнеса.',
                        'Привлеките экспертов для формализации планов. Внедрите регулярное обновление стратегии, чтобы адаптироваться к рыночным изменениям.',
                        'Проведите стратегические сессии с менеджерами для выработки единого видения. Это укрепит доверие инвесторов.'
                    ],
                    'plan' => [
                        'Нанять консультанта по стратегии (1 месяц).',
                        'Провести стратегические сессии (1–2 месяца).',
                        'Разработать стратегию с KPI (2–3 месяца).',
                        'Настроить процесс обновления планов (1 месяц).',
                        'Подготовить план экспансии (3–6 месяцев).'
                    ]
                ];
            } elseif ($area['name'] === 'Размер и рыночная позиция') {
                $recommendations[] = [
                    'paragraphs' => [
                        'Для усиления рыночной позиции сосредоточьтесь на конкурентных преимуществах. Проведите детальный анализ рынка, чтобы выявить ниши для роста.',
                        'Разработайте план масштабирования операций, включая новые продукты или регионы. Укрепите клиентскую базу через таргетированные маркетинговые кампании.',
                        'Рассмотрите стратегические партнерства или поглощения для увеличения доли рынка. Постоянно отслеживайте конкурентов.'
                    ],
                    'plan' => [
                        'Провести анализ рынка (1–2 месяца).',
                        'Разработать план масштабирования (2–3 месяца).',
                        'Запустить маркетинговую кампанию (2–4 месяца).',
                        'Найти партнеров для сотрудничества (3–6 месяцев).',
                        'Оценить конкурентов (1 месяц).'
                    ]
                ];
            } elseif ($area['name'] === 'Финансовая готовность') {
                $recommendations[] = [
                    'paragraphs' => [
                        'Финансовая готовность нуждается в доработке. Перейдите на МСФО-отчетность, чтобы соответствовать стандартам публичных компаний.',
                        'Организуйте регулярный внешний аудит у аккредитованной фирмы. Создайте детализированную финансовую модель на 5–10 лет, учитывающую сценарии роста и рисков.',
                        'Обучите финансовую команду работе с инвесторами. Снизьте долговую нагрузку, если она превышает целевые показатели.'
                    ],
                    'plan' => [
                        'Нанять аудитора для оценки отчетности (1 месяц).',
                        'Начать переход на МСФО (3–6 месяцев).',
                        'Разработать финансовую модель (2–3 месяца).',
                        'Провести обучение команды (1–2 месяца).',
                        'Оптимизировать долговую нагрузку (3–12 месяцев).'
                    ]
                ];
            } elseif ($area['name'] === 'Организационная готовность') {
                $recommendations[] = [
                    'paragraphs' => [
                        'Организационная структура требует оптимизации. Внедрите службу внутреннего аудита для контроля процессов.',
                        'Разработайте регламенты управления рисками, чтобы минимизировать угрозы. Создайте долгосрочную систему мотивации для ключевых сотрудников через опционы или акции.',
                        'Проведите обучение менеджеров по стандартам управления публичных компаний. Убедитесь, что все процессы задокументированы.'
                    ],
                    'plan' => [
                        'Создать службу внутреннего аудита (2–3 месяца).',
                        'Разработать регламенты рисков (1–2 месяца).',
                        'Внедрить систему мотивации (2–3 месяца).',
                        'Организовать обучение менеджеров (1–2 месяца).',
                        'Документировать процессы (2–4 месяца).'
                    ]
                ];
            } elseif ($area['name'] === 'Юридическая готовность') {
                $recommendations[] = [
                    'paragraphs' => [
                        'Юридическая готовность компании требует улучшений. Завершите консолидацию всех активов под единым эмитентом, чтобы упростить структуру собственности.',
                        'Рассмотрите переход на статус ПАО, если это еще не сделано. Проведите юридический аудит, чтобы устранить потенциальные риски.',
                        'Подготовьте документы для регуляторов заранее. Это минимизирует препятствия на пути к IPO.'
                    ],
                    'plan' => [
                        'Нанять юриста для аудита (1 месяц).',
                        'Завершить консолидацию активов (3–6 месяцев).',
                        'Подготовить переход на ПАО (2–4 месяца).',
                        'Провести аудит документов (1–2 месяца).',
                        'Подготовить бумаги для регуляторов (2–3 месяца).'
                    ]
                ];
            } elseif ($area['name'] === 'Готовность к коммуникациям') {
                $recommendations[] = [
                    'paragraphs' => [
                        'Коммуникационная стратегия нуждается в развитии. Создайте полноценный IR-раздел на сайте с финансовой информацией и презентациями.',
                        'Разработайте PR-стратегию с акцентом на Investor Relations, включая публикации в СМИ. Назначьте спикеров для публичных выступлений и обучите их.',
                        'Регулярно обновляйте соцсети и публичные каналы, чтобы повысить узнаваемость.'
                    ],
                    'plan' => [
                        'Создать IR-раздел на сайте (1–2 месяца).',
                        'Разработать PR-стратегию (2–3 месяца).',
                        'Назначить и обучить спикеров (1–2 месяца).',
                        'Запустить активность в соцсетях (2–4 месяца).',
                        'Подготовить медиаплан (1 месяц).'
                    ]
                ];
            }
        }
    }

    // Добавление дефолтной рекомендации, если нет специфичных
    if (empty($recommendations)) {
        $recommendations[] = $defaultRecommendation;
    }

    // Генерация PDF и отправка email
    $emailSent = false;
    $emailError = null;
    $error = null;
    try {
        // Вызов функции для создания PDF
        $pdfFile = generatePDF(compact(
            'strategicScore', 'marketScore', 'financialScore', 'orgScore', 'legalScore', 'commScore',
            'maxStrategic', 'maxMarket', 'maxFinancial', 'maxOrg', 'maxLegal', 'maxComm',
            'strategicPercent', 'marketPercent', 'financialPercent', 'orgPercent', 'legalPercent', 'commPercent',
            'totalScore', 'maxTotal', 'totalPercent', 'readinessLevel', 'generalAssessment', 'recommendations'
        ));

        // Сохранение пути к PDF в сессии
        $_SESSION['pdfFile'] = $pdfFile;

        // Формирование контактной информации для email
        $contactInfo = "Контактные данные:\n" .
                       "Имя: {$_SESSION['name']}\n" .
                       "Компания: {$_SESSION['company']}\n" .
                       "Должность: {$_SESSION['position']}\n" .
                       "Телефон: {$_SESSION['phone']}\n" .
                       "Email: {$_SESSION['email']}";

        // Настройка параметров email
        $to = $_SESSION['email'];
        $bcc = 'vv@polaris-capital.ru';
        $subject = "Оценка готовности к IPO от МОЕХ";
        $message = "Ваш отчет о готовности к IPO во вложении.\n\n$contactInfo";
        $boundary = md5(time());
        $headers = "From: no-reply@vitvin.online\r\n";
        $headers .= "Bcc: $bcc\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

        // Формирование тела письма с вложением
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message . "\r\n";

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/pdf; name=\"report.pdf\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"report.pdf\"\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($pdfFile))) . "\r\n";
        $body .= "--$boundary--";

        // Отправка email
        if (mail($to, $subject, $body, $headers)) {
            $emailSent = true;
        } else {
            $emailError = "Не удалось отправить отчет на email.";
            error_log("Failed to send email to $to", 3, '/tmp/email_errors.log');
        }
    } catch (Exception $e) {
        $error = "Ошибка при генерации отчета: " . $e->getMessage();
        error_log("PDF generation error: " . $e->getMessage(), 3, '/tmp/tcpdf_errors.log');
    }

    // Сохранение результатов в сессии
    $_SESSION['result'] = compact(
        'strategicScore', 'marketScore', 'financialScore', 'orgScore', 'legalScore', 'commScore',
        'maxStrategic', 'maxMarket', 'maxFinancial', 'maxOrg', 'maxLegal', 'maxComm',
        'strategicPercent', 'marketPercent', 'financialPercent', 'orgPercent', 'legalPercent', 'commPercent',
        'totalScore', 'maxTotal', 'totalPercent', 'readinessLevel', 'generalAssessment', 'recommendations',
        'emailSent', 'emailError', 'error'
    );
}

// Обработка POST-запроса формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    processForm($_POST);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <!-- Мета-теги для кодировки и адаптивности -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оценка готовности к IPO</title>
    <!-- Подключение Chart.js для отображения диаграммы -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Импорт шрифта Roboto */
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap');

        /* Основные стили страницы */
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #F5F6F5 0%, #E8ECEF 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            background-color: #FFFFFF;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 48, 135, 0.1);
            transition: transform 0.3s ease;
        }

        .container:hover {
            transform: translateY(-2px);
        }

        .logo {
            display: block;
            margin: 0 auto 30px;
            max-width: 200px;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        h1 {
            text-align: center;
            color: #003087;
            font-size: 28px;
            font-weight: 500;
            margin-bottom: 30px;
            letter-spacing: 0.5px;
        }

        h2 {
            color: #003087;
            font-size: 22px;
            font-weight: 500;
            margin-top: 40px;
            margin-bottom: 20px;
        }

        .section-title {
            color: #003087;
            font-size: 24px;
            font-weight: 500;
            margin-top: 50px;
            margin-bottom: 25px;
            border-left: 4px solid #005DAA;
            padding-left: 15px;
        }

        .intro-text {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #F9FAFB;
            border-radius: 8px;
            font-size: 16px;
            color: #1A202C;
            line-height: 1.6;
        }

        .question {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #F9FAFB;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .question:hover {
            background-color: #F1F5F9;
        }

        .question label {
            display: block;
            font-size: 16px;
            font-weight: 500;
            color: #1A202C;
            margin-bottom: 12px;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .radio-option label {
            display: flex;
            align-items: center;
            font-size: 15px;
            color: #1A202C;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }

        .radio-option label:hover {
            background-color: rgba(0, 93, 170, 0.05);
        }

        .radio-option input[type="radio"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
            accent-color: #003087;
        }

        .contact-info {
            margin-top: 40px;
            padding: 20px;
            background-color: #F9FAFB;
            border-radius: 8px;
        }

        .contact-field {
            margin-bottom: 20px;
        }

        .contact-field input[type="text"],
        .contact-field input[type="tel"],
        .contact-field input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #C0C0C0;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 15px;
            color: #1A202C;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .contact-field input[type="text"]:focus,
        .contact-field input[type="tel"]:focus,
        .contact-field input[type="email"]:focus {
            border-color: #005DAA;
            box-shadow: 0 0 8px rgba(0, 93, 170, 0.2);
            outline: none;
        }

        .contact-field input[type="text"]::placeholder,
        .contact-field input[type="tel"]::placeholder,
        .contact-field input[type="email"]::placeholder {
            color: #999999;
        }

        .contact-field label {
            display: flex;
            align-items: center;
            font-size: 15px;
            color: #1A202C;
        }

        .contact-field input[type="checkbox"] {
            margin-right: acabado 12px;
            width: 18px;
            height: 18px;
            accent-color: #003087;
        }

        button {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #003087 0%, #005DAA 100%);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 48, 135, 0.2);
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 48, 135, 0.3);
        }

        button:disabled {
            background: #A0A0A0;
            cursor: not-allowed;
            box-shadow: none;
        }

        .result {
            margin-top: 40px;
            padding: 25px;
            background-color: #F9FAFB;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .result h2 {
            margin-top: 0;
        }

        .result h4 {
            color: #003087;
            font-size: 18px;
            margin: 20px 0 10px;
        }

        .result p {
            margin: 10px 0;
            font-size: 15px;
            color: #1A202C;
        }

        .result ol {
            padding-left: 25px;
            font-size: 15px;
            color: #1A202C;
            margin: 10px 0;
        }

        .result canvas {
            margin: 20px auto;
            width: 400px !important;
            height: 400px !important;
            padding: 10px;
            background-color: #FFFFFF;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .consultation-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #F9FAFB;
            border-radius: 8px;
        }

        .success {
            color: #28A745;
            font-size: 15px;
            font-weight: 500;
            padding: 10px;
            background-color: #E6F4EA;
            border-radius: 6px;
        }

        .error {
            color: #D32F2F;
            font-size: 15px;
            font-weight: 500;
            padding: 10px;
            background-color: #FDECEA;
            border-radius: 6px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #hourglassModal {
            z-index: 1001; /* Выше стандартного модала */
        }

        .modal-content {
            background-color: #FFFFFF;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
        }

        .modal-content p {
            font-size: 16px;
            color: #1A202C;
            margin: 0 0 20px;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            color: #666666;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #003087;
        }

        /* Стили для песочных часов */
        .hourglass-modal-content {
            background-color: #FFFFFF; /* Белый фон для видимости */
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .hourglass {
            width: 80px;
            height: 120px;
            position: relative;
            margin: 0 auto;
        }

        .hourglass::before,
        .hourglass::after {
            content: '';
            position: absolute;
            width: 0;
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
        }

        .hourglass::before {
            border-bottom: 50px solid #003087;
            top: 0;
            transform: rotate(180deg);
        }

        .hourglass::after {
            border-top: 50px solid #003087;
            bottom: 0;
        }

        .hourglass .sand-top,
        .hourglass .sand-bottom {
            position: absolute;
            width: 40px;
            left: 20px;
            background: #FFC107; /* Яркий жёлтый для видимости */
        }

        .hourglass .sand-top {
            height: 40px;
            top: 10px;
            border-bottom: 40px solid #FFC107;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            animation: sand-flow 3s linear forwards;
        }

        .hourglass .sand-bottom {
            height: 0;
            bottom: 10px;
            border-top: 0 solid #FFC107;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            animation: sand-accumulate 3s linear forwards;
        }

        .hourglass .frame {
            position: absolute;
            top: 0;
            left: 0;
            width: 80px;
            height: 120px;
            background: linear-gradient(90deg, #005DAA, #003087);
            clip-path: polygon(
                0 0, 20px 0, 20px 40px, 60px 40px, 60px 0, 80px 0, 
                80px 120px, 60px 120px, 60px 80px, 20px 80px, 20px 120px, 0 120px
            );
            z-index: 1; /* Рамка выше песка */
        }

        @keyframes sand-flow {
            0% {
                height: 40px;
                opacity: 1;
            }
            100% {
                height: 0;
                opacity: 0;
            }
        }

        @keyframes sand-accumulate {
            0% {
                height: 0;
                opacity: 0;
            }
            100% {
                height: 40px;
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Основной контейнер страницы -->
    <div class="container">
        <!-- Логотип Московской Биржи -->
        <img src="moex.png" alt="Логотип Московской Биржи" class="logo">
        <h1>Оценка готовности к IPO</h1>
        <!-- Вводный текст -->
        <div class="intro-text">
            <p>Качественная подготовка к IPO напрямую влияет на успех сделки и отношение инвесторов к компании на долгосрочном горизонте после обретения публичного статуса.</p>
            <p>Заполнение опросника займёт у вас около 10 минут. В результате вы получите индивидуальные комментарии по готовности вашей компании к IPO.</p>
        </div>
        <!-- Форма для ввода ответов и контактных данных -->
        <form id="ipoForm" action="index.php" method="post">
            <?php
            // Массив вопросов и вариантов ответа
            $questions = [
                1 => [
                    'text' => 'Какова цель IPO вашей компании?',
                    'options' => [
                        ['text' => 'Привлечение капитала для роста', 'value' => 20],
                        ['text' => 'Продажа части акций', 'value' => 20],
                        ['text' => 'Замещение долга', 'value' => 10],
                        ['text' => 'Повышение узнаваемости бренда', 'value' => 10],
                        ['text' => 'Другое', 'value' => 0]
                    ]
                ],
                2 => [
                    'text' => 'Какова стратегия вашей компании?',
                    'options' => [
                        ['text' => 'Стратегия формализована и является инструментом управления', 'value' => 30],
                        ['text' => 'Есть документ, но не обновляется', 'value' => 9],
                        ['text' => 'Нет документированной стратегии', 'value' => 0]
                    ]
                ],
                3 => [
                    'text' => 'Готова ли компания к прозрачности?',
                    'options' => [
                        ['text' => 'Готовы к раскрытию по стандартам публичных компаний', 'value' => 30],
                        ['text' => 'Раскрытие возможно по требованию регуляторов и по запросу инвесторов', 'value' => 9],
                        ['text' => 'Готовы раскрывать только самый минимум', 'value' => 9]
                    ]
                ],
                4 => [
                    'text' => 'Какова дивидендная политика компании?',
                    'options' => [
                        ['text' => 'Чётко задокументирована и согласована с акционерами', 'value' => 3],
                        ['text' => 'Выплачиваем дивиденды по решению общего собрания участников', 'value' => 2],
                        ['text' => 'Формально прописана, но не соблюдается', 'value' => 1],
                        ['text' => 'Не определена', 'value' => 0]
                    ]
                ],
                5 => [
                    'text' => 'Какова выручка компании за последний отчётный период?',
                    'options' => [
                        ['text' => 'более 50 млрд руб.', 'value' => 30],
                        ['text' => '10 - 50 млрд руб.', 'value' => 30],
                        ['text' => '1 - 10 млрд руб.', 'value' => 15],
                        ['text' => 'до 1 млрд руб.', 'value' => 0]
                    ]
                ],
                6 => [
                    'text' => 'Каков темп прироста выручки за последний год?',
                    'options' => [
                        ['text' => 'Рост свыше 30%', 'value' => 20],
                        ['text' => 'Рост 10 - 30%', 'value' => 16],
                        ['text' => 'Рост менее 10%', 'value' => 6],
                        ['text' => 'Нет роста или падение', 'value' => 0]
                    ]
                ],
                7 => [
                    'text' => 'Каково лидерство компании на рынке?',
                    'options' => [
                        ['text' => 'Компания лидирует в нескольких нишах или на широком рынке', 'value' => 30],
                        ['text' => 'Компания лидер в своей нише/вертикали', 'value' => 30],
                        ['text' => 'Компания входит в топ 10 игроков', 'value' => 15],
                        ['text' => 'Компания не оценивала свою рыночную позицию', 'value' => 0]
                    ]
                ],
                8 => [
                    'text' => 'Какова прибыльность компании?',
                    'options' => [
                        ['text' => 'Маржа по чистой прибыли свыше 20%', 'value' => 20],
                        ['text' => 'Маржа по чистой прибыли 5 - 20%', 'value' => 16],
                        ['text' => 'Маржа по чистой прибыли менее 5%', 'value' => 6],
                        ['text' => 'Другое', 'value' => 0]
                    ]
                ],
                9 => [
                    'text' => 'Какова уникальность продукта и технологическое преимущество?',
                    'options' => [
                        ['text' => 'Компания демонстрирует технологическое лидерство и потенциал масштабирования', 'value' => 30],
                        ['text' => 'Продукты компании обладают значимой ценностью для клиентов, но не уникальны', 'value' => 24],
                        ['text' => 'Компания ничем не выделяется среди конкурентов', 'value' => 0]
                    ]
                ],
                10 => [
                    'text' => 'Как ведётся финансовая отчётность компании?',
                    'options' => [
                        ['text' => 'Есть аудит и консолидированная МСФО отчётность за 1-3 года', 'value' => 30],
                        ['text' => 'Есть не аудированная МСФО отчётность', 'value' => 15],
                        ['text' => 'В процессе трансформации в МСФО', 'value' => 15],
                        ['text' => 'Только РСБУ', 'value' => 9],
                        ['text' => 'Только управленческая отчётность группы', 'value' => 0]
                    ]
                ],
                11 => [
                    'text' => 'Как ведётся управленческая отчётность?',
                    'options' => [
                        ['text' => 'Управленческая отчётность полностью автоматизирована', 'value' => 30],
                        ['text' => 'Управленческая отчётность частично автоматизирована', 'value' => 15],
                        ['text' => 'Ведётся, но нерегулярно, составляем формы по необходимости', 'value' => 9],
                        ['text' => 'Нет регулярного управленческого учёта', 'value' => 0]
                    ]
                ],
                12 => [
                    'text' => 'Имеется ли финансовая модель компании?',
                    'options' => [
                        ['text' => 'Финансовая модель, построенная по принципам МСФО на 5-10 лет вперед', 'value' => 30],
                        ['text' => 'Финансовая модель, построенная по денежным потокам', 'value' => 15],
                        ['text' => 'Есть базовая модель без сценариев на 3 года вперед', 'value' => 9],
                        ['text' => 'Нет формализованной финмодели', 'value' => 0]
                    ]
                ],
                13 => [
                    'text' => 'Какова долговая нагрузка компании?',
                    'options' => [
                        ['text' => 'Не имеем долговых обязательств', 'value' => 20],
                        ['text' => 'Долг/EBITDA менее 2х', 'value' => 20],
                        ['text' => 'Долг/EBITDA от 3 до 4х', 'value' => 10],
                        ['text' => 'Не применимо', 'value' => 20]
                    ]
                ],
                14 => [
                    'text' => 'Проводится ли внешний аудит?',
                    'options' => [
                        ['text' => 'Аудит у компании из большой четверки', 'value' => 30],
                        ['text' => 'Аудит у аккредитованной ЦБ компании', 'value' => 24],
                        ['text' => 'Аудит не проводится', 'value' => 0]
                    ]
                ],
                15 => [
                    'text' => 'Как функционирует совет директоров?',
                    'options' => [
                        ['text' => 'СД работает и соответствует требованиям для публичных компаний', 'value' => 10],
                        ['text' => 'СД играет неформальную роль', 'value' => 5],
                        ['text' => 'СД не сформирован', 'value' => 0],
                        ['text' => 'Не применимо', 'value' => 0]
                    ]
                ],
                16 => [
                    'text' => 'Как организован внутренний аудит и контроль рисков?',
                    'options' => [
                        ['text' => 'Действующая служба ВА, регламенты управления рисками', 'value' => 10],
                        ['text' => 'Частично внедрён', 'value' => 5],
                        ['text' => 'Не внедрён', 'value' => 0]
                    ]
                ],
                17 => [
                    'text' => 'Каковы бизнес-процессы и цифровизация?',
                    'options' => [
                        ['text' => 'Бизнес-процессы отлажены, используются лучшие практики управления', 'value' => 20],
                        ['text' => 'Бизнес-процессы отлажены, но требуется дополнительная работа', 'value' => 16],
                        ['text' => 'Автоматизировали и оцифровали некоторые процессы', 'value' => 6],
                        ['text' => 'Обходимся традиционными методами управления', 'value' => 0]
                    ]
                ],
                18 => [
                    'text' => 'Какова система мотивации ключевого персонала?',
                    'options' => [
                        ['text' => 'Используется долгосрочная система мотивации, основанная на опционах/акциях', 'value' => 20],
                        ['text' => 'Разработана система KPI, поощряем денежными бонусами', 'value' => 16],
                        ['text' => 'Нет', 'value' => 0]
                    ]
                ],
                19 => [
                    'text' => 'Создана ли рабочая группа для подготовки к IPO?',
                    'options' => [
                        ['text' => 'Выбран профессиональный консультант', 'value' => 30],
                        ['text' => 'Есть внутренняя рабочая группа', 'value' => 24],
                        ['text' => 'Пока нет', 'value' => 0]
                    ]
                ],
                20 => [
                    'text' => 'Какова правовая форма потенциального эмитента?',
                    'options' => [
                        ['text' => 'ПАО (или готовы к перерегистрации)', 'value' => 30],
                        ['text' => 'АО', 'value' => 24],
                        ['text' => 'ООО', 'value' => 15]
                    ]
                ],
                21 => [
                    'text' => 'Какова юридическая структура компании?',
                    'options' => [
                        ['text' => 'Все компании группы консолидированы под потенциальным эмитентом', 'value' => 30],
                        ['text' => 'В процессе консолидации', 'value' => 15],
                        ['text' => 'Бизнес ведётся на разных юрлицах', 'value' => 0]
                    ]
                ],
                22 => [
                    'text' => 'Какова PR-стратегия и план коммуникаций?',
                    'options' => [
                        ['text' => 'Разработана с учётом IPO и включает Investor Relations', 'value' => 30],
                        ['text' => 'Полностью сформирована и успешно реализуется применительно к клиентам компании', 'value' => 24],
                        ['text' => 'Частично сформирована', 'value' => 15],
                        ['text' => 'Отсутствует', 'value' => 0]
                    ]
                ],
                23 => [
                    'text' => 'Имеется ли корпоративный сайт с IR-разделом?',
                    'options' => [
                        ['text' => 'Разработан и регулярно обновляется полноценный IR-раздел с финансовой информацией и инвестиционными презентациями', 'value' => 20],
                        ['text' => 'Есть базовый корпоративный сайт', 'value' => 10],
                        ['text' => 'Корпоративного сайта нет/на сайте нет раздела о компании', 'value' => 0]
                    ]
                ],
                24 => [
                    'text' => 'Как ведутся социальные сети и публичные каналы?',
                    'options' => [
                        ['text' => 'Системная работа, единая тональность', 'value' => 20],
                        ['text' => 'Ведутся нерегулярно', 'value' => 10],
                        ['text' => 'Не ведутся', 'value' => 0]
                    ]
                ],
                25 => [
                    'text' => 'Как организованы спикеры и публичные выступления?',
                    'options' => [
                        ['text' => 'Назначены спикеры, регулярно выступают на публичных мероприятиях', 'value' => 20],
                        ['text' => 'Нет единой политики и назначенных спикеров', 'value' => 6],
                        ['text' => 'Не назначены', 'value' => 0]
                    ]
                ]
            ];

            // Определение заголовков секций
            $sections = [
                1 => 'Стратегическая готовность',
                5 => 'Размер и рыночная позиция',
                10 => 'Финансовая готовность',
                15 => 'Организационная готовность',
                20 => 'Юридическая готовность',
                22 => 'Готовность к коммуникациям'
            ];

            // Генерация HTML для вопросов
            foreach ($questions as $i => $q): ?>
                <?php if (isset($sections[$i])): ?>
                    <h2 class="section-title"><?php echo htmlspecialchars($sections[$i]); ?></h2>
                <?php endif; ?>
                <div class="question">
                    <label>Вопрос <?php echo $i; ?>: <?php echo htmlspecialchars($q['text']); ?></label>
                    <div class="radio-group">
                        <?php foreach ($q['options'] as $index => $option): ?>
                            <div class="radio-option">
                                <label>
                                    <input type="radio" 
                                           name="q<?php echo $i; ?>" 
                                           value="<?php echo $option['value']; ?>" 
                                           <?php echo isset($_SESSION["q$i"]) && $_SESSION["q$i"] == $option['value'] ? 'checked' : ''; ?>
                                           required>
                                    <?php echo htmlspecialchars($option['text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <!-- Контактная информация -->
            <div class="contact-info">
                <h2>Контактная информация</h2>
                <div class="contact-field">
                    <input type="text" name="name" placeholder="Имя" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>
                </div>
                <div class="contact-field">
                    <input type="text" name="company" placeholder="Компания" value="<?php echo htmlspecialchars($_SESSION['company'] ?? ''); ?>" required>
                </div>
                <div class="contact-field">
                    <input type="text" name="position" placeholder="Должность" value="<?php echo htmlspecialchars($_SESSION['position'] ?? ''); ?>" required>
                </div>
                <div class="contact-field">
                    <input type="tel" name="phone" placeholder="Телефон" value="<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>" required>
                </div>
                <div class="contact-field">
                    <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                </div>
            </div>
            <!-- Кнопка отправки формы -->
            <button type="submit" id="submitBtn">Рассчитать баллы</button>
        </form>
        <!-- Модальное окно для уведомления -->
        <div id="modal" class="modal">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <p>Детальный отчет будет отправлен на указанный адрес электронной почты.</p>
            </div>
        </div>
        <!-- Модальное окно для песочных часов -->
        <div id="hourglassModal" class="modal">
            <div class="hourglass-modal-content">
                <div class="hourglass">
                    <div class="sand-top"></div>
                    <div class="sand-bottom"></div>
                    <div class="frame"></div>
                </div>
            </div>
        </div>
        <!-- Отображение результатов -->
        <?php if (isset($_SESSION['result']) && !empty($_SESSION['result'])): ?>
            <div class="result">
                <h2>Результаты:</h2>
                <canvas id="radarChart" width="400" height="400"></canvas>
                <br>
                <p>Стратегическая готовность: <?php echo $_SESSION['result']['strategicScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxStrategic'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['strategicPercent']) ? number_format($_SESSION['result']['strategicPercent'], 2) : 'N/A'; ?>%)</p>
                <br>
                <p>Размер и рыночная позиция: <?php echo $_SESSION['result']['marketScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxMarket'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['marketPercent']) ? number_format($_SESSION['result']['marketPercent'], 2) : 'N/A'; ?>%)</p>
                <br>
                <p>Финансовая готовность: <?php echo $_SESSION['result']['financialScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxFinancial'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['financialPercent']) ? number_format($_SESSION['result']['financialPercent'], 2) : 'N/A'; ?>%)</p>
                <br>
                <p>Организационная готовность: <?php echo $_SESSION['result']['orgScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxOrg'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['orgPercent']) ? number_format($_SESSION['result']['orgPercent'], 2) : 'N/A'; ?>%)</p>
                <br>
                <p>Юридическая готовность: <?php echo $_SESSION['result']['legalScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxLegal'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['legalPercent']) ? number_format($_SESSION['result']['legalPercent'], 2) : 'N/A'; ?>%)</p>
                <br>
                <p>Готовность к коммуникациям: <?php echo $_SESSION['result']['commScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxComm'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['commPercent']) ? number_format($_SESSION['result']['commPercent'], 2) : 'N/A'; ?>%)</p>
                <br>
                <p><strong>Общий балл: <?php echo $_SESSION['result']['totalScore'] ?? 'N/A'; ?> из <?php echo $_SESSION['result']['maxTotal'] ?? 'N/A'; ?> (<?php echo isset($_SESSION['result']['totalPercent']) ? number_format($_SESSION['result']['totalPercent'], 2) : 'N/A'; ?>%)</strong></p>
                <br>
                <p><strong>Уровень готовности: <?php echo $_SESSION['result']['readinessLevel'] ?? 'N/A'; ?></strong></p>
                <br>
                <h3>Общий анализ:</h3>
                <p><?php echo isset($_SESSION['result']['generalAssessment']) ? nl2br(htmlspecialchars($_SESSION['result']['generalAssessment'])) : 'N/A'; ?></p>
                <br>
                <h3>Рекомендации:</h3>
                <?php if (isset($_SESSION['result']['recommendations']) && is_array($_SESSION['result']['recommendations'])): ?>
                    <?php foreach ($_SESSION['result']['recommendations'] as $rec): ?>
                        <?php foreach ($rec['paragraphs'] as $para): ?>
                            <p><?php echo htmlspecialchars($para); ?></p>
                        <?php endforeach; ?>
                        <h4>Пошаговый план:</h4>
                        <ol>
                            <?php foreach ($rec['plan'] as $item): ?>
                                <li><?php echo htmlspecialchars($item); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Рекомендации отсутствуют</p>
                <?php endif; ?>
                <br>
                <?php if (isset($_SESSION['result']['emailSent']) && $_SESSION['result']['emailSent']): ?>
                    <p class="success">Отчет успешно отправлен на ваш email.</p>
                <?php elseif (isset($_SESSION['result']['emailError'])): ?>
                    <p class="error"><?php echo htmlspecialchars($_SESSION['result']['emailError']); ?></p>
                <?php endif; ?>
                <?php if (isset($_SESSION['result']['error'])): ?>
                    <p class="error"><?php echo htmlspecialchars($_SESSION['result']['error']); ?></p>
                <?php endif; ?>
                <!-- Секция для запроса консультации -->
                <div class="consultation-section">
                    <h2>Получить консультацию</h2>
                    <div class="contact-field">
                        <label>
                            <input type="checkbox" name="consultation" id="consultation" <?php echo isset($_SESSION['consultation']) && $_SESSION['consultation'] ? 'checked' : ''; ?>>
                            Я хочу получить бесплатную консультацию по результатам оценки. Я даю согласие на обработку моих персональных данных.
                        </label>
                    </div>
                    <button type="button" id="sendConsultation" <?php echo isset($_SESSION['consultation']) && $_SESSION['consultation'] ? '' : 'disabled'; ?>>Отправить запрос</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script>
        // Инициализация обработчиков после загрузки DOM
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('ipoForm');
            const submitBtn = document.getElementById('submitBtn');
            const modal = document.getElementById('modal');
            const hourglassModal = document.getElementById('hourglassModal');
            const modalClose = document.querySelector('.modal-close');
            const consultationCheckbox = document.getElementById('consultation');
            const sendConsultationBtn = document.getElementById('sendConsultation');

            // Обработка отправки формы
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    hourglassModal.style.display = 'flex';
                    setTimeout(() => {
                        hourglassModal.style.display = 'none';
                        modal.style.display = 'flex';
                        setTimeout(() => {
                            form.submit();
                        }, 2000);
                    }, 3000);
                });
            }

            // Закрытие модального окна
            if (modalClose) {
                modalClose.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }

            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Обработка запроса на консультацию
            if (consultationCheckbox && sendConsultationBtn) {
                consultationCheckbox.addEventListener('change', function() {
                    sendConsultationBtn.disabled = !this.checked;
                });

                sendConsultationBtn.addEventListener('click', function() {
                    const formData = new URLSearchParams();
                    formData.append('consultation', consultationCheckbox.checked);
                    formData.append('name', '<?php echo addslashes($_SESSION['name'] ?? ''); ?>');
                    formData.append('company', '<?php echo addslashes($_SESSION['company'] ?? ''); ?>');
                    formData.append('position', '<?php echo addslashes($_SESSION['position'] ?? ''); ?>');
                    formData.append('phone', '<?php echo addslashes($_SESSION['phone'] ?? ''); ?>');
                    formData.append('email', '<?php echo addslashes($_SESSION['email'] ?? ''); ?>');

                    fetch('send_consultation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            alert('Запрос на консультацию успешно отправлен!');
                        } else {
                            alert('Ошибка при отправке запроса: ' + (data.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Произошла ошибка при отправке запроса: ' + error.message);
                    });
                });
            }

            // Плавная прокрутка к диаграмме при загрузке результатов
            <?php if (isset($_SESSION['result']) && !empty($_SESSION['result'])): ?>
                const radarChart = document.getElementById('radarChart');
                if (radarChart) {
                    setTimeout(() => {
                        radarChart.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                }

                // Создание радарной диаграммы
                const ctx = document.getElementById('radarChart').getContext('2d');
                new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: [
                            'Стратегическая',
                            'Рыночная',
                            'Финансовая',
                            'Организационная',
                            'Юридическая',
                            'Коммуникационная'
                        ],
                        datasets: [{
                            label: 'Готовность к IPO (%)',
                            data: [
                                <?php echo isset($_SESSION['result']['strategicPercent']) ? number_format($_SESSION['result']['strategicPercent'], 2) : 0; ?>,
                                <?php echo isset($_SESSION['result']['marketPercent']) ? number_format($_SESSION['result']['marketPercent'], 2) : 0; ?>,
                                <?php echo isset($_SESSION['result']['financialPercent']) ? number_format($_SESSION['result']['financialPercent'], 2) : 0; ?>,
                                <?php echo isset($_SESSION['result']['orgPercent']) ? number_format($_SESSION['result']['orgPercent'], 2) : 0; ?>,
                                <?php echo isset($_SESSION['result']['legalPercent']) ? number_format($_SESSION['result']['legalPercent'], 2) : 0; ?>,
                                <?php echo isset($_SESSION['result']['commPercent']) ? number_format($_SESSION['result']['commPercent'], 2) : 0; ?>
                            ],
                            backgroundColor: 'rgba(0, 48, 135, 0.2)',
                            borderColor: '#003087',
                            borderWidth: 2,
                            pointBackgroundColor: '#003087'
                        }]
                    },
                    options: {
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    stepSize: 20,
                                    font: {
                                        size: 12,
                                        family: 'Roboto'
                                    }
                                },
                                grid: {
                                    color: '#C0C0C0'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 14,
                                        family: 'Roboto'
                                    }
                                }
                            }
                        }
                    }
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>
<?php
// Очистка результатов после отображения
unset($_SESSION['result']);
?>