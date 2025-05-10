<?php

namespace App\Traits;

trait DocumentUtilityTrait
{
    protected function wrapInHtmlDocument(string $content, string $title): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
            font-size: 12pt;
        }
        h1 {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 24pt;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        h2 {
            font-size: 14pt;
            margin-top: 16pt;
        }
        p {
            margin-bottom: 10pt;
            text-align: justify;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 70px;
            margin-bottom: 10px;
        }
        @media print {
            body {
                font-size: 12pt;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    {$content}
</body>
</html>
HTML;
    }

    protected function cleanHtmlContent(string $content): string
    {
        $content = preg_replace('/^```(?:html)?\s*\n/m', '', $content);
        
        $content = preg_replace('/\n```\s*$/m', '', $content);
        
        return trim($content);
    }

    protected function formatDateString(string $dateString): string
    {
        $indonesianMonths = [
            'januari' => 'january',
            'februari' => 'february',
            'maret' => 'march',
            'april' => 'april',
            'mei' => 'may',
            'juni' => 'june',
            'juli' => 'july',
            'agustus' => 'august',
            'september' => 'september',
            'oktober' => 'october',
            'november' => 'november',
            'desember' => 'december',
        ];
        
        $lowerDateString = strtolower($dateString);
        foreach ($indonesianMonths as $indo => $eng) {
            if (str_contains($lowerDateString, $indo)) {
                $lowerDateString = str_replace($indo, $eng, $lowerDateString);
                break;
            }
        }

        try {
            $timestamp = strtotime($lowerDateString);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
            
            $date = new \DateTime($lowerDateString);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return date('Y-m-d');
        }
    }

    protected function extractTextFromPDF($file): string
    {
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file->getPathname());
                return $pdf->getText();
            } catch (\Exception $e) {
                return "Error extracting PDF text: " . $e->getMessage();
            }
        }
        
        $content = file_get_contents($file->getPathname());
        
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\xFF]/', ' ', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        if (preg_match_all('/\/Text\s*\[(.*?)\]/', $content, $matches)) {
            return implode(' ', $matches[1]);
        }
        
        return "PDF content extract (limited): " . substr(strip_tags($content), 0, 5000);
    }
    
    protected function extractTextFromWord($file): string
    {
        if (class_exists('\PhpOffice\PhpWord\IOFactory')) {
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($file->getPathname());
                $text = '';
                
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                            $text .= $element->getText() . ' ';
                        } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                            foreach ($element->getElements() as $textRunElement) {
                                if ($textRunElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                    $text .= $textRunElement->getText() . ' ';
                                }
                            }
                        } elseif ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                            foreach ($element->getRows() as $row) {
                                foreach ($row->getCells() as $cell) {
                                    foreach ($cell->getElements() as $cellElement) {
                                        if ($cellElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                            $text .= $cellElement->getText() . ' ';
                                        } elseif ($cellElement instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                            foreach ($cellElement->getElements() as $textRunElement) {
                                                if ($textRunElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                                    $text .= $textRunElement->getText() . ' ';
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                return $text;
            } catch (\Exception $e) {
                return "Error extracting Word document text: " . $e->getMessage();
            }
        }
        
        if ($file->getClientOriginalExtension() == 'docx') {
            $content = '';
            $zip = new \ZipArchive();
            
            if ($zip->open($file->getPathname())) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $content = $zip->getFromIndex($index);
                    $content = preg_replace('/<w:p[^>]*>/', "\n", $content);
                    $content = preg_replace('/<[^>]*>/', '', $content);
                    $content = html_entity_decode($content);
                }
                $zip->close();
            }
            
            return empty($content) ? "Unable to extract DOCX content" : $content;
        }
        
        return "Word document content (needs phpoffice/phpword for extraction)";
    }

    protected function extractKeyValuePairs(string $text): array
    {
        $result = [
            'judul' => '',
            'tanggal' => '',
            'pihak' => '',
            'pihak2' => '',
            'perjanjian' => '',
            'deskripsi' => '',
            'htmlSummary' => '',
        ];
        
        if (preg_match('/(?:judul|title)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['judul'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:tanggal|date)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['tanggal'] = trim($matches[1]);
            $result['tanggal'] = $this->formatDateString($result['tanggal']);
        }
        
        if (preg_match('/(?:pihak pertama|first party|pihak)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['pihak'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:pihak kedua|second party|pihak2)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['pihak2'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:perjanjian|agreement type|agreement)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['perjanjian'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:deskripsi|description)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['deskripsi'] = trim($matches[1]);
        } elseif (preg_match('/(?:summary)[\s:]+([^\n]+)/i', $text, $matches)) {
            $result['deskripsi'] = trim($matches[1]);
        }
        
        if (preg_match('/<html.*?>.*?<\/html>/is', $text, $matches) || 
            preg_match('/<div.*?>.*?<\/div>/is', $text, $matches) || 
            preg_match('/<p>.*?<\/p>/is', $text, $matches)) {
            $result['htmlSummary'] = trim($matches[0]);
        } else {
            $result['htmlSummary'] = '<p>' . ($result['deskripsi'] ?: 'No summary available') . '</p>';
        }
        
        return $result;
    }
}

