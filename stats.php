<?php
// stats.php — compact horizontal SVG card with ISO codes

// Load visitor data
$data = file_exists("data.json") ? json_decode(file_get_contents("data.json"), true) : [];
$visitorCounts = $data["countries"] ?? [];

// Sort and slice
arsort($visitorCounts);
$top10 = array_slice($visitorCounts, 0, 10, true);
$total = array_sum($visitorCounts);

// Layout
$cols = 5; // max per row
$itemWidth = 90;
$itemHeight = 50;
$padding = 15;

$rows = ceil(count($top10) / $cols);
$width = $padding * 2 + ($cols * $itemWidth);
$height = 60 + ($rows * $itemHeight);

header("Content-Type: image/svg+xml");
echo "<?xml version='1.0' encoding='UTF-8'?>";
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?=$width?>" height="<?=$height?>">
  <style>
    text { font-family: sans-serif; }
  </style>
  <rect width="100%" height="100%" fill="white" stroke="black" rx="8" ry="8"/>
  <text x="10" y="25" font-size="18" fill="blue">Visitor Stats</text>
  <text x="10" y="45" font-size="14" fill="black">Total: <?=$total?></text>
<?php
$i = 0;
foreach ($top10 as $iso => $count):
    $flag = "https://flagcdn.com/w40/".strtolower($iso).".png";

    $row = floor($i / $cols);
    $col = $i % $cols;
    $x = $padding + $col * $itemWidth;
    $y = 70 + $row * $itemHeight;
?>
  <image x="<?=$x?>" y="<?=$y?>" width="40" height="30" href="<?=$flag?>"/>
  <text x="<?=$x+45?>" y="<?=$y+20?>" font-size="14"><?=$iso?></text>
  <text x="<?=$x+45?>" y="<?=$y+35?>" font-size="12">(<?=$count?>)</text>
<?php
    $i++;
endforeach;
?>
</svg>
