<?php

require_once 'src/Approximation.php';

function printFloat($value): string
{
    return sprintf('%.6f', $value);
}

# Запускаем обработку входных данных
if (isset($_GET['calculate'])) {
    header('Content-Type: application/json');

    if (!isset($_FILES['input_csv'])) {
        die('no input file');
    }

    $x = [];
    $y = [];

    $handle = @fopen($_FILES['input_csv']['tmp_name'], "r");
    if ($handle) {
        while (($buffer = fgets($handle, 4096)) !== false) {
            $buffer = str_replace([',',';'], ['.',','], $buffer);
            $data = json_decode('['.$buffer.']');
            $x[] = $data[0];
            $y[] = $data[1];
        }
        if (!feof($handle)) {
            die("Ошибка: fgets() неожиданно потерпел неудачу\n");
        }
        fclose($handle);
    }

    $plotData = [
        [
            'label' => 'source',
            'clickable' => true,
            'hoverable' => true,
            'data' => [],
        ],
        [
            'label' => 'result',
            'clickable' => true,
            'hoverable' => true,
            'data' => [],
        ],
    ];

    $ort = (new Approximation($x, $y, (int)$_REQUEST['measure'] ?? 2))->init();
    $result = $ort->getRegress();

    foreach ($x as $xKey => $xPoint) {
        $plotData[0]['data'][] = [$xPoint, $y[$xKey]];
        $plotData[1]['data'][] = [$xPoint, $result[$xKey]];
    }

    $coefficients = $ort->getCoefficients();
    array_walk($coefficients, static function (&$arCoefficients) {
        $arCoefficients = array_map(static function ($arItem) {
            return printFloat($arItem);
        }, $arCoefficients);
    });

    $discrepancyData = $ort->getDiscrepancy($result);

    $data = [
        'plot'          => $plotData,
        'coefficients'  => $coefficients,
        'discrepancy'   => printFloat($discrepancyData['discrepancy']).'%',
        'deviation'     => printFloat($discrepancyData['deviation']),
        'determination' => printFloat($ort->getDeterminationCoefficient($result)),
    ];

    echo json_encode($data);
    return;
}

?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <script type="text/javascript" src="./js/jquery.min.js"></script>
    <script type="text/javascript" src="./js/jquery.flot.js"></script>

    <title>Практика. МНК</title>
</head>
<body>
<div class="container">
    <h3 class="mt-3">Практика. МНК</h3>

    <form action="?calculate" id="form" enctype="multipart/form-data" class="mt-5">
        <div class="mb-3 row">
            <label for="inputMeasure" class="col-sm-2 col-form-label">Полином</label>
            <div class="col-sm-10">
                <input type="number" name="measure" id="inputMeasure" class="form-control" value="10" required>
            </div>
        </div>
        <div class="mb-3 row">
            <label for="inputFile" class="col-sm-2 col-form-label">Входной файл</label>
            <div class="col-sm-10">
                <input type="file" id="inputFile" name="input_csv" class="form-control" required>
            </div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-3 text-center">
                <b>Скачать примеры файлов:</b>
            </div>
            <div class="col-sm-3 text-center">
                <a href="input1.csv" download="true">input1.csv</a>
            </div>
            <div class="col-sm-3 text-center">
                <a href="input2.csv" download="true">input2.csv</a>
            </div>
            <div class="col-sm-3 text-center">
                <a href="input3.csv" download="true">input3.csv</a>
            </div>
        </div>
        <div class="mb-3 row">
            <div class="col-sm-12">
                <button type="submit" class="btn btn-primary mb-3 w-100">Продолжить</button>
            </div>
        </div>
    </form>

    <!-- Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Результаты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="placeholder" style="width:100%;height:500px"></div>
                    <div class="mt-5"></div>

                    <div class="mb-3 row">
                        <label for="outputDiscrepancy" class="col-sm-6 col-form-label">Величина макс. относительной ошибки</label>
                        <div class="col-sm-6">
                            <input type="text" readonly id="outputDiscrepancy" class="form-control-plaintext" value="0%">
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="outputDeviation" class="col-sm-6 col-form-label">Наибольшее отклонение</label>
                        <div class="col-sm-6">
                            <input type="text" readonly id="outputDeviation" class="form-control-plaintext" value="0">
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label for="outputDetermination" class="col-sm-6 col-form-label">Коэффициент детерминации</label>
                        <div class="col-sm-6">
                            <input type="text" readonly id="outputDetermination" class="form-control-plaintext" value="0">
                        </div>
                    </div>

                    <div class="m-3 row">
                        <table class="table">
                            <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">коэффициенты при степенях регрессионного полинома</th>
                            </tr>
                            </thead>
                            <tbody id="coefficients-table"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
        const $placeholder = $("#placeholder");

        $('#form').on('submit', function (event) {
            event.preventDefault();

            const $form = $(this);
            const formData = new FormData(this);

            $placeholder.html('');

            $.ajax({
                type: 'POST',
                url: $form.attr('action'),
                data: formData,
                dataType: 'json',
                cache: false,
                contentType: false,
                processData: false,
            }).done(function (data) {
                console.log(data);

                resultModal.show();
                setTimeout(function () {
                    data.plot = data.plot || {};
                    $.plot("#placeholder", data.plot, {
                        series: {
                            lines: {
                                show: true
                            },
                            points: {
                                show: true
                            }
                        },
                        grid: {
                            hoverable: true,
                            clickable: true
                        },
                    });

                    data.discrepancy = data.discrepancy || '';
                    $("#outputDiscrepancy").val(data.discrepancy || '');

                    data.deviation = data.deviation || '';
                    $("#outputDeviation").val(data.deviation || '');

                    data.determination = data.determination || '';
                    $("#outputDetermination").val(data.determination || '');

                    data.coefficients = data.coefficients || {};
                    data.coefficients.a = data.coefficients.a || [];
                    data.coefficients.b = data.coefficients.b || [];
                    data.coefficients.c = data.coefficients.c || [];

                    const $coefficientsTable = $("#coefficients-table");
                    $coefficientsTable.html('');

                    data.coefficients.a.forEach(function (currentValue, index) {
                        let a = currentValue;
                        let b = typeof data.coefficients.b[index] !== "undefined" ? data.coefficients.b[index] : 0;
                        let c = typeof data.coefficients.c[index] !== "undefined" ? data.coefficients.c[index] : 0;

                        $("<tr><th scope='row'>"+index+"</th><td>"+a+"</td></tr>").appendTo($coefficientsTable);
                    });

                }, 1000);
            }).fail(console.error);
        });

        $("<div id='tooltip'></div>").css({
            position: "absolute",
            display: "none",
            border: "1px solid #fdd",
            padding: "2px",
            "background-color": "#fee",
            opacity: 0.80,
            "z-index": 9999,
        }).appendTo("body");

        const $tooltip = $("#tooltip");

        $placeholder.bind("plothover", function (event, pos, item) {
            if (item) {
                let x = item.datapoint[0].toFixed(2),
                    y = item.datapoint[1].toFixed(2);

                $tooltip
                    .html(item.series.label + " of " + x + " = " + y)
                    .css({top: item.pageY+5, left: item.pageX+5})
                    .fadeIn(200);
            } else {
                $tooltip.hide();
            }
        });
    });
</script>

<!-- Optional JavaScript; choose one of the two! -->

<!-- Option 1: Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

<!-- Option 2: Separate Popper and Bootstrap JS -->
<!--
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
-->
</body>
</html>
