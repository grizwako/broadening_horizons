<?php
require __DIR__ . '/vendor/autoload.php';
use function Functional\group;
use function Functional\map;
use function Functional\reindex;

//http://data.un.org/

//Total employment, by economic activity (Thousands)  [1980-2008]
//http://data.un.org/Data.aspx?q=employment&d=LABORSTA&f=tableCode%3a2B


//https://github.com/nikic/iter
//https://github.com/lstrojny/functional-php

/**
 * Creates dictionary-like closure which accesses array values by string keys.
 * So instead of $row[$map['key'] you can use $row('key')
 * For example $entry = mappedRow(
 *      [4, 'bla@blo.com'],
 *      ['id' => 0, 'email' => '1]
 *  );
 * will allow you to call $entry('email') later
 *
 * @param $row
 * @param $columnMap
 * @return Closure
 */
function mappedRow($row, $columnMap)
{
    if(empty($columnMap)) {
        throw new LogicException('Column map must not be empty! Hint: reverse header row to use as column map');
    }
    //we could return object with either pre-filled properties or with magical getter that would use column map
    //but as we are already talking about functional programming

    //returns function which accepts one parameter and has saved values for $row and $columnMap
    return function($key = null) use ($row, $columnMap) {
        return $row[$columnMap[$key]];
    };
}

function mappedCsvGenerator($filename, $split = ',', $maxLineLength = 0, $columnMap = [])
{
    $fileHandle = fopen($filename,'r');
    if(FALSE === $fileHandle) {
        throw new Exception('Could not open file: '.$filename);
    }
    while (($data = fgetcsv($fileHandle, $maxLineLength, $split)) !== FALSE) {
        if(empty($columnMap)) {
            //Moves array pointer to next row
            $columnMap = array_flip($data);
            continue;
        }

        yield mappedRow($data, $columnMap);
    }
}


$rows = mappedCsvGenerator('un_employment_1980-2008.csv');


$stats = iter\filter(
    function($row) {
        return $row('Country or Area') === 'Croatia' && $row('Sex') === 'Total men and women';
    },
    $rows
);

$statsBySubclassification = group($stats, function($row){
    return $row('Subclassification');
});


$statsBySubclassificationAndYear = map($statsBySubclassification, function($subclass) {
    $indexed = reindex($subclass, function($row) {
        return (int)$row('Year');
    });
    return map($indexed, function($row) {
        return (float)$row('Value');
    });
});


$totalByYear = $statsBySubclassificationAndYear['Total.'];
$years = array_keys($totalByYear);
sort($years); // Why is this bad style?

//now, lets build table that will show us suclassifications percentages over years
// I feel that foreach is cleaner here, because we are mutating some object
$table = new SimpleXMLElement('<table/>');
$head = $table->addChild('tr');
$head->addChild('th', 'Subclassification');
foreach($years as $year) {
    $head->addChild('th', $year);
}

foreach($statsBySubclassificationAndYear as $subclass => $byYear) {
    $tableRow = $table->addChild('tr');
    $tableRow->addChild('td', $subclass);
    $percentage = 0;
    foreach($years as $year) {
        if(array_key_exists($year, $byYear)) {
            // can this part of code be improved by using functional style?
            $tempPerc = 100 *($byYear[$year] / $totalByYear[$year]);
            $delta = $tempPerc - $percentage;
            $percentage = $tempPerc;
            $procFormat = number_format($percentage, 2);
            $deltaFormat = number_format($delta, 2);
            $tableRow->addChild('td', $procFormat)->addChild('p', $deltaFormat);
        } else {
            $tableRow->addChild('td', ' - ');
        }
    }
}


?>

<style>
    table, th, td {
        border: 1px solid black;
    }
    p {
        color: blueviolet;
    }
</style>

Percentages by sector, blue is delta from previous year
<?php echo $table->asXML(); ?>