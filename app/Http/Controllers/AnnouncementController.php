<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        // Mark announcements as seen for staff users (Admin/Superadmin) so their
        // header badge reflects only announcements created or updated after this visit.
        session(['staff_last_seen_announcement_at' => now()]);

        $announcements = Announcement::orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        $editingId = $request->query('edit');
        $editingAnnouncement = null;
        if (!empty($editingId)) {
            $editingAnnouncement = Announcement::find($editingId);
        }

        $tableData = $announcements->map(function (Announcement $a) {
            $startsAt = $a->starts_at ? $a->starts_at->format('Y-m-d') : 'Immediately';
            $endsAt = $a->ends_at ? $a->ends_at->format('Y-m-d') : 'Open';
            // Use a simple ASCII dash between dates to avoid encoding issues
            $period = $startsAt . ' - ' . $endsAt;

            $csrf = csrf_token();
            $editUrl = route('announcements', ['edit' => $a->id]);

            $actions = '<a href="' . $editUrl . '" class="btn btn-outline-primary btn-sm me-1">Edit</a>';
            $actions .= '<form method="POST" action="' . route('announcements.delete', ['id' => $a->id]) . '" style="display:inline-block;" data-confirm="Delete this announcement?">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<input type="hidden" name="_method" value="DELETE">'
                . '<button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>'
                . '</form>';

            $titleHtml = '<div class="announcement-admin-preview"'
                . ' data-edit-url="' . e($editUrl) . '"'
                . ' data-title="' . e($a->title) . '"'
                . ' data-body="' . e($a->body ?? '') . '"'
                . ' data-period="' . e($period) . '">' .
                '<div class="fw-semibold">' . e($a->title) . '</div>' .
                '<div class="small text-muted">' . e(\Illuminate\Support\Str::limit($a->body ?? '', 120)) . '</div>' .
                '</div>';

            return [
                $titleHtml,
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
            'editingAnnouncement' => $editingAnnouncement,
        ]);
    }

    /**
     * Read-only announcements list for staff roles (HR, Supervisor, Admin, Superadmin, etc.).
     * Used by the header announcements icon.
     */
    public function staffIndex(Request $request)
    {
        // Mark announcements as seen for staff so the header badge clears.
        session(['staff_last_seen_announcement_at' => now()]);

        $announcements = Announcement::orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        $tableData = $announcements->map(function (Announcement $a) {
            $startsAt = $a->starts_at ? $a->starts_at->format('Y-m-d') : 'Immediately';
            $endsAt = $a->ends_at ? $a->ends_at->format('Y-m-d') : 'Open';
            // Use a simple ASCII dash between dates to avoid encoding issues
            $period = $startsAt . ' - ' . $endsAt;

            $bodyPreview = \Illuminate\Support\Str::limit($a->body ?? '', 120);

            return [
                '<div class="announcement-preview" data-title="' . e($a->title) . '" data-body="' . e($a->body ?? '') . '" data-period="' . e($period) . '">' .
                    '<div class="fw-semibold announcement-preview-title">' . e($a->title) . '</div>' .
                    '<div class="small text-muted announcement-preview-body">' . e($bodyPreview) . '</div>' .
                '</div>',
                $period,
            ];
        })->toArray();

        return view('pages.staff-announcements', [
            'title' => 'Announcements',
            'pageClass' => 'staff-announcements',
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

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('announcements', ['edit' => $id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $announcement->update($validated);

        return redirect()->route('announcements')
            ->with('success', 'Announcement updated successfully.');
    }

    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return redirect()->route('announcements')
            ->with('success', 'Announcement deleted successfully.');
    }
}
