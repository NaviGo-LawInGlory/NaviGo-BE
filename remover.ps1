# Script to remove non-essential comments from PHP files while preserving important ones

$projectRoot = "D:\navigo-be"
$phpFiles = Get-ChildItem -Path $projectRoot -Filter "*.php" -Recurse

function Is-ImportantComment {
    param (
        [string]$commentText
    )
    
    $patterns = @(
        'godoc',
        'swagger:',
        '@\w+',
        '\+\w+',
        'TODO:',
        'FIXME:',
        'NOTE:',
        'filepath:',
        'license',
        'copyright'
    )
    
    foreach ($pattern in $patterns) {
        if ($commentText -match $pattern) {
            return $true
        }
    }
    
    return $false
}

$totalProcessed = 0
$totalModified = 0

foreach ($file in $phpFiles){
    Write-Host "Processing: $($file.FullName)"
    $totalProcessed++
    
    $content = Get-Content -Path $file.FullName -Raw
    $lines = $content -split "`r`n|\r|\n"
    $newLines = @()
    $fileModified = $false
    
    for ($lineIndex = 0; $lineIndex -lt $lines.Length; $lineIndex++) {
        $line = $lines[$lineIndex]
       
        if ([string]::IsNullOrWhiteSpace($line)) {
            $newLines += $line
            continue
        }
        
        if ($line -match '^\s*//') {
            if (Is-ImportantComment -commentText $line) {
                $newLines += $line
            } else {
                $fileModified = $true
            }
            continue
        }
        
        if ($line -match '^\s*/\*') {
            $commentBlockLines = @($line)
            $isImportantBlock = Is-ImportantComment -commentText $line
            
            while ($lineIndex + 1 -lt $lines.Length -and -not ($lines[$lineIndex] -match '\*/\s*$')) {
                $lineIndex++
                $commentBlockLines += $lines[$lineIndex]
                
                if (-not $isImportantBlock -and (Is-ImportantComment -commentText $lines[$lineIndex])) {
                    $isImportantBlock = $true
                }
            }
            
            if ($isImportantBlock) {
                $newLines += $commentBlockLines
            } else {
                $fileModified = $true
            }
            continue
        }
        
        $commentPos = -1
        $inString = $false
        $stringChar = ''
        $escape = $false
        
        for ($i = 0; $i -lt $line.Length - 1; $i++) {
            $char = $line[$i]
            $nextChar = $line[$i + 1]
            
            if (($char -eq '"' -or $char -eq "'") -and -not $escape) {
                if (-not $inString) {
                    $inString = $true
                    $stringChar = $char
                } elseif ($char -eq $stringChar) {
                    $inString = $false
                }
            }
            
            $escape = ($char -eq '\' -and -not $escape)
            
            if (-not $inString -and $char -eq '/' -and $nextChar -eq '/') {
                $commentPos = $i
                break
            }
        }
        
        if ($commentPos -eq -1) {
            $newLines += $line
            continue
        }
        
        $commentPart = $line.Substring($commentPos)
        $codePart = $line.Substring(0, $commentPos).TrimEnd()
        
        $hasUrl = $commentPart -match 'https?://'
        
        if ($hasUrl -or (Is-ImportantComment -commentText $commentPart)) {
            $newLines += $line
        } else {
            $newLines += $codePart
            $fileModified = $true
        }
    }
    
    if ($fileModified) {
        $totalModified++
        Set-Content -Path $file.FullName -Value ($newLines -join [Environment]::NewLine)
        Write-Host "  Modified: Comments removed" -ForegroundColor Green
    } else {
        Write-Host "  Unchanged: No comments removed" -ForegroundColor Gray
    }
}

Write-Host "`nSummary:" -ForegroundColor Cyan
Write-Host "Files processed: $totalProcessed" -ForegroundColor White
Write-Host "Files modified: $totalModified" -ForegroundColor Green
Write-Host "`nComment removal completed! Important comments were preserved." -ForegroundColor Cyan