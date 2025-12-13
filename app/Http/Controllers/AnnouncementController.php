<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        $tableData = $announcements->map(function (Announcement $a) {
            $startsAt = $a->starts_at ? $a->starts_at->format('Y-m-d') : 'Immediately';
            $endsAt = $a->ends_at ? $a->ends_at->format('Y-m-d') : 'Open';

            $csrf = csrf_token();

            $actions = '<form method="POST" action="' . route('announcements.delete', ['id' => $a->id]) . '" style="display:inline-block;" onsubmit="return confirm(\'Delete this announcement?\');">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<input type="hidden" name="_method" value="DELETE">'
                . '<button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>'
                . '</form>';

            return [
                '<div class="fw-semibold">' . e($a->title) . '</div>' .
                '<div class="small text-muted">' . e(\Illuminate\Support\Str::limit($a->body ?? '', 120)) . '</div>',
                $startsAt,
                $endsAt,
                $actions,
            ];
        })->toArray();

        return view('pages.announcements', [
            'title' => 'Announcements',
            'pageClass' => 'announcements',
            'announcements' => $announcements,
            'announcementTableData' => $tableData,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        Announcement::create($validated);

        return redirect()->route('announcements')
            ->with('success', 'Announcement created successfully.');
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return redirect()->route('announcements')
            ->with('success', 'Announcement deleted successfully.');
    }
}
