<?php

namespace App\Http\Controllers;

use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

//Exportar excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//Exportar en pdf
use Barryvdh\DomPDF\Facade\Pdf;

//Exportar en Word
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class UnidadMedidaController extends Controller
{

    function __construct()
    {
        $this->middleware('permission:lista-unidadmedida|ver-unidadmedida|crear-unidadmedida|editar-unidadmedida|borrar-unidadmedida', ['only' => ['index']]);
        $this->middleware('permission:crear-unidadmedida', ['only' => ['create', 'store']]);
        $this->middleware('permission:editar-unidadmedida', ['only' => ['edit', 'update']]);
        $this->middleware('permission:borrar-unidadmedida', ['only' => ['destroy']]);
        $this->middleware('permission:ver-unidadmedida', ['only' => ['show']]);
    }

    public function index()
    {
        try {
            $unidadmedida = UnidadMedida::all();
            return view('unidadmedida.index', compact('unidadmedida'));
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al cargar los datos: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            //mensajes personalizados
            $messages = [
                'descripcion.required' => 'La descripcion es obligatorio.',
                'descripcion.unique' => 'La descripcion ya está en uso.',
                'abreviacion.required' => 'La abreviacion es obligatorio.',
                'abreviacion.unique' => 'La abreviacion ya está en uso.',
            ];

            // Validar los datos del formulario
            $validatedData = $request->validate([
                'descripcion' => [
                    'required',
                    'string',
                    'max:50',
                    'min:5',
                    Rule::unique('unidad_medida'), // Validación de unicidad
                ],
                'abreviacion' => [
                    'required',
                    'string',
                    'max:4',
                    'min:1',
                    Rule::unique('unidad_medida'), // Validación de unicidad
                ],
            ], $messages);

            $unidadmedida = UnidadMedida::create($validatedData);

            // Redirigir con un mensaje de éxito
            return redirect()->route('unidadmedida.index')->with('success', 'Unidad de Medida creada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al crear la unidad de medida: ' . $e->getMessage());
        }
    }

    public function show(UnidadMedida $unidadmedida)
    {
    }

    public function edit($id)
    {
        try {
            $unidadmedi = UnidadMedida::findOrFail($id);
            return response()->json($unidadmedi);
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al cargar la vista de edición: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            //mensajes personalizados
            $messages = [
                'descripcion.required' => 'La descripcion es obligatorio.',
                'abreviacion.required' => 'La abreviacion es obligatorio.',
            ];

            // Validar los datos del formulario
            $validatedData = $request->validate([
                'descripcion' => [
                    'required',
                    'string',
                    'max:50',
                    'min:5',

                ],
                'abreviacion' => [
                    'required',
                    'string',
                    'max:4',
                    'min:1',

                ],
            ], $messages);

            $unidadmedi = UnidadMedida::findOrFail($id);
            $unidadmedi->update($validatedData);

            return redirect()->route('unidadmedida.index')->with('success', 'Unidad de Medida actualizada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al actualizar la unidad de medida: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $unidadmedi = UnidadMedida::findOrFail($id);
            $unidadmedi->delete();

            // Devolver una respuesta adecuada para confirmar la eliminación
            return response()->json(['mensaje' => 'Unidad de Medida eliminada correctamente']);
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrio un error al eliminar' . $e->getMessage());
        }
    }
    public function export(Request $request)
    {
        try {
            $format = $request->query('format', 'excel'); // Default to excel if no format is specified

            switch ($format) {
                case 'pdf':
                    return $this->exportToPdf();
                case 'word':
                    return $this->exportToWord();
                default:
                    return $this->exportToExcel();
            }
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al exportar los datos: ' . $e->getMessage());
        }
    }

    public function exportToExcel()
    {
        try {
            $unidadesMedida = UnidadMedida::with(['audit.usercreated', 'audit.userupdated'])->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'ID');
            $sheet->setCellValue('B1', 'Descripción');
            $sheet->setCellValue('C1', 'Abreviación');
            $sheet->setCellValue('D1', 'Usuario que Creó');
            $sheet->setCellValue('E1', 'Usuario que Actualizó');
            $sheet->setCellValue('F1', 'Fecha de Creación');
            $sheet->setCellValue('G1', 'Fecha de Actualización');

            $row = 2;
            foreach ($unidadesMedida as $medida) {
                $sheet->setCellValue('A' . $row, $medida->id);
                $sheet->setCellValue('B' . $row, $medida->descripcion);
                $sheet->setCellValue('C' . $row, $medida->abreviacion);
                $sheet->setCellValue('D' . $row, $medida->audit ? ($medida->audit->usercreated ? $medida->audit->usercreated->email : 'N/A') : 'N/A');
                $sheet->setCellValue('E' . $row, $medida->audit ? ($medida->audit->userupdated ? $medida->audit->userupdated->email : 'N/A') : 'N/A');
                $sheet->setCellValue('F' . $row, $medida->created_at);
                $sheet->setCellValue('G' . $row, $medida->updated_at);
                $row++;
            }

            foreach (range('A', 'G') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="unidades_medida.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al exportar los datos a Excel: ' . $e->getMessage());
        }
    }


    public function exportToPdf()
    {
        try {
            $unidadesmedidas = UnidadMedida::with(['audit.usercreated', 'audit.userupdated'])->get();
            $pdf = Pdf::loadView('unidadmedida.pdf', compact('unidadesmedidas'));

            // Personalizar la configuración del documento PDF
            $pdf->setPaper('A4', 'landscape'); // Configurar orientación horizontal y tamaño A4

            // Descargar el PDF con un nombre de archivo específico
            return $pdf->download('unidadmedida.pdf');
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al exportar los datos a PDF: ' . $e->getMessage());
        }
    }

    public function exportToWord()
    {
        try {
            $unidadesMedida = UnidadMedida::with(['audit.usercreated', 'audit.userupdated'])->get();

            $phpWord = new PhpWord();
            // Ajustar la sección actual para orientación horizontal
            $section = $phpWord->addSection([
                'orientation' => 'landscape', // Orientación horizontal
            ]);

            // Estilo para encabezados en negrita
            $headerStyle = ['bold' => true];

            $section->addText('Lista de Unidades de Medida', ['bold' => true, 'size' => 16]);

            // Configurar tabla con anchos relativos
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 80,
                'width' => 100 * 50, // Ancho de la tabla en puntos
            ]);
            // Encabezados de tabla en negrita y centrados
            $table->addRow();
            $table->addCell(800)->addText('ID', $headerStyle);
            $table->addCell(2000)->addText('Descripción', $headerStyle);
            $table->addCell(2000)->addText('Abreviación', $headerStyle);
            $table->addCell(4000)->addText('Usuario que Creó', $headerStyle);
            $table->addCell(2000)->addText('Usuario que Actualizó', $headerStyle);
            $table->addCell(2000)->addText('Fecha de Creación', $headerStyle);
            $table->addCell(2000)->addText('Fecha de Actualización', $headerStyle);

            foreach ($unidadesMedida as $medida) {
                $table->addRow();
                $table->addCell(800)->addText($medida->id);
                $table->addCell(2000)->addText($medida->descripcion);
                $table->addCell(2000)->addText($medida->abreviacion);
                $table->addCell(4000)->addText($medida->audit ? ($medida->audit->usercreated ? $medida->audit->usercreated->email : 'N/A') : 'N/A');
                $table->addCell(2000)->addText($medida->audit ? ($medida->audit->userupdated ? $medida->audit->userupdated->email : 'N/A') : 'N/A');
                $table->addCell(2000)->addText($medida->created_at);
                $table->addCell(2000)->addText($medida->updated_at);
            }

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment;filename="unidadesmedidas.docx"');
            header('Cache-Control: max-age=0');

            $objWriter->save('php://output');
        } catch (\Exception $e) {
            return redirect()->route('unidadmedida.index')->with('error', 'Ocurrió un error al exportar los datos a Word: ' . $e->getMessage());
        }
    }
}
