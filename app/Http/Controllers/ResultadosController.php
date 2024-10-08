<?php

namespace App\Http\Controllers;

use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use App\Models\Resultados;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Grades;
use App\Models\ResultadosDisciplinas;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResultadosController extends Controller
{

    public function index()
    {
        $resultados = Resultados::with(['gradeAntiga', 'gradeNova'])->orderBy('created_at', 'desc')->get();

        return Inertia::render('Resultados/Index', ['resultados' => $resultados]);
    }

    public function show($id)
    {
        $resultado = Resultados::with(['disciplinasCursadas', 'disciplinasAbatidas', 'disciplinasAtribuidas', 'gradeAntiga', 'gradeNova'])->find($id);

        return Inertia::render('Resultados/Resultado', ['resultado' => $resultado]);
    }

    function store($resultado_request)
    {
        try {

            DB::beginTransaction();

            $resultado_criado = Resultados::create([
                'titulo' => $resultado_request['titulo'],
                'grade_antiga' => $resultado_request['grade_antiga']['id'],
                'grade_nova' => $resultado_request['grade_nova']['id'],
            ]);

            foreach ($resultado_request['disciplinas_cursadas_grade_antiga'] as $disciplina_antiga) {
                //this one comes as array instead of object, due to altered carga_horaria
                ResultadosDisciplinas::create([
                    'resultados_id' => $resultado_criado->id,
                    'disciplinas_id' => (int)$disciplina_antiga['id'],
                    'tipo' => 'cursada',
                    'carga_horaria' => (int)$disciplina_antiga['carga_horaria'] ?? 0
                ]);
            }


            if ($resultado_request['disciplinas_abatidas_grade_nova']) {
                foreach ($resultado_request['disciplinas_abatidas_grade_nova'] as $disciplina_abatida) {
                    ResultadosDisciplinas::create([
                        'resultados_id' => $resultado_criado->id,
                        'disciplinas_id' => $disciplina_abatida->id,
                        'tipo' => 'abatida',
                        'carga_horaria' => $disciplina_abatida->carga_horaria ?? 0
                    ]);
                }
            }

            foreach ($resultado_request['disciplinas_a_cursar_grade_nova'] as $disciplina_a_cursar) {
                ResultadosDisciplinas::create([
                    'resultados_id' => $resultado_criado->id,
                    'disciplinas_id' => $disciplina_a_cursar->id,
                    'tipo' => 'atribuida',
                    'carga_horaria' => $disciplina_a_cursar->carga_horaria ?? 0
                ]);
            }


            DB::commit();

            return $resultado_criado;
        } catch (Throwable $e) {
            DB::rollBack();
            return 'error';
        }
    }

    public function createPdf($resultado_id)
    {
        $resultado = Resultados::with(['disciplinasCursadas', 'disciplinasAbatidas', 'disciplinasAtribuidas', 'gradeAntiga', 'gradeNova'])->find($resultado_id);

        $path = storage_path('app/public/' . $resultado->titulo . '.pdf');

        Pdf::view('resultado_pdf', ['resultado' => $resultado])->save($path);

        return response()->download($path);
    }


    // public function createPdf($resultado_id)
    // {
    //     // Retrieve the data
    //     $resultado = Resultados::with([
    //         'disciplinasCursadas',
    //         'disciplinasAbatidas',
    //         'disciplinasAtribuidas',
    //         'gradeAntiga',
    //         'gradeNova'
    //     ])->find($resultado_id);

    //     // Render the Blade view to HTML
    //     $html = view('resultado_pdf', ['resultado' => $resultado])->render();

    //     // Define the path where the PDF will be saved
    //     $path = storage_path('app/public/' . $resultado->titulo . '.pdf');

    //     // Create the PDF using Chromium with Browsershot
    //     Browsershot::html($html)
    //         ->setNodeBinary('/usr/bin/node') // Path to Node.js
    //         ->setNpmBinary('/usr/bin/npm') // Path to NPM
    //         ->setChromePath('/usr/bin/chromium') // Path to Chromium
    //         ->setOption('userDataDir', '/tmp/chrome')
    //         ->addChromiumArguments([
    //             '--disable-breakpad',
    //             '--no-sandbox',
    //             '--disable-gpu',
    //             '--disable-software-rasterizer',
    //             '--disable-dev-shm-usage',
    //             '--no-crashpad',
    //             '--headless'
    //         ])
    //         ->savePdf($path);

    //     // Return the generated PDF as a downloadable file
    //     return response()->download($path);
    // }
}
