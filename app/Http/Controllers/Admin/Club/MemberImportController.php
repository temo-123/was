<?php

namespace App\Http\Controllers\Admin\Club;

use App\Http\Controllers\Controller;
use App\Services\Club\MemberImporter;
use App\Services\Club\MemberSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/** Upload → preview (new / already added / errors) → confirm. */
class MemberImportController extends Controller
{
    public function create(): View
    {
        return view('admin.club.members.import');
    }

    public function preview(Request $request, MemberSpreadsheet $spreadsheet, MemberImporter $importer): View|RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, MemberSpreadsheet::EXTENSIONS, true)) {
            return back()->withErrors(['file' => __('Unsupported file type. Use .xlsx, .ods or .csv (old .xls files: open in Excel and "Save as" .xlsx).')]);
        }

        try {
            $rows = $spreadsheet->read($file->getRealPath(), $extension);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['file' => __('The file could not be read. Is it a valid Excel file?')]);
        }

        if (! $rows) {
            return back()->withErrors(['file' => __('The file has no member rows under the header.')]);
        }

        // The parsed rows wait on disk until the import is confirmed.
        $key = (string) Str::uuid();
        Storage::disk('local')->put($this->path($key), json_encode($rows));
        $request->session()->put('club_import', $key);

        $items = $importer->analyze($rows);

        return view('admin.club.members.preview', [
            'items' => $items,
            'summary' => MemberImporter::summary($items),
            'key' => $key,
            'fileName' => $file->getClientOriginalName(),
        ]);
    }

    public function store(Request $request, MemberImporter $importer): RedirectResponse
    {
        $key = (string) $request->input('key');
        $disk = Storage::disk('local');

        if ($key === '' || $request->session()->get('club_import') !== $key || ! $disk->exists($this->path($key))) {
            return redirect()->route('admin.club.import.create')->with('error', __('The import expired. Please upload the file again.'));
        }

        $rows = json_decode($disk->get($this->path($key)), true);
        $counts = $importer->import($rows, $request->user());

        $disk->delete($this->path($key));
        $request->session()->forget('club_import');

        return redirect()->route('admin.club.members.index')->with('success', __('Imported :created new members. Skipped: :duplicates already added, :invalid with errors.', $counts));
    }

    private function path(string $key): string
    {
        return 'club-imports/'.$key.'.json';
    }
}
