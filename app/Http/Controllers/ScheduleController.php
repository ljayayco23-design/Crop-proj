<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Replaces FarmerScheduleController. Shared by both the farmer and
 * technician "Schedule" pages — same `schedules` table, same Schedule
 * model. Every method is scoped to auth()->id(), and every method also
 * re-checks auth()->user()->role server-side (defense in depth on top of
 * the 'role:farmer' / 'role:technician' route middleware in web.php) —
 * same belt-and-suspenders pattern already used by FarmerReportController.
 *
 * FARMER side (index/events/sync) is unchanged: offline-first, the page
 * keeps its own IndexedDB copy and this reconciles batched changes.
 *
 * TECHNICIAN side (technicianIndex/technicianEvents/technicianStore/
 * technicianUpdate/technicianDestroy) is online-only, plain CRUD — no
 * offline queue, no service worker. That machinery is farmer-only; see
 * the comment at the top of technician/schedule.blade.php.
 */
class ScheduleController extends Controller
{
    private const TYPES    = ['inspection', 'treatment', 'maintenance', 'followup', 'other'];
    private const STATUSES = ['approved', 'pending', 'completed'];
    private const COLORS   = ['blue', 'green', 'amber', 'purple', 'red', 'teal', 'gray'];

    // ============================================================
    // FARMER — offline-first (identical to the old FarmerScheduleController)
    // ============================================================

    public function index()
    {
        abort_unless(auth()->user()->role === 'farmer', 403);
        return view('farmer.schedule', ['uid' => auth()->id()]);
    }

    /** Pull-only. Also hands the page a fresh CSRF token after it has been offline for a while. */
    public function events()
    {
        abort_unless(auth()->user()->role === 'farmer', 403);
        return response()->json($this->payload())->header('Cache-Control', 'no-store');
    }

    /**
     * Two-way sync in one request.
     *  - "changes": rows the device created/edited/deleted while offline (max 200 per call).
     *  - Reply:     this farmer's full, current list, so the device can merge it.
     * Conflict rule: the most recent edit (client_updated_at) wins.
     */
    public function sync(Request $request)
    {
        abort_unless(auth()->user()->role === 'farmer', 403);

        $changes = $request->input('changes', []);
        abort_unless(is_array($changes), 422);

        $uid = auth()->id();
        $cap = (int) (microtime(true) * 1000) + 60000; // ignore device clocks set far in the future

        foreach (array_slice($changes, 0, 200) as $c) {
            if (!is_array($c) || !isset($c['uuid']) || !is_string($c['uuid']) || !Str::isUuid($c['uuid'])) {
                continue;
            }

            $ts  = min((int) ($c['updated_at'] ?? 0), $cap);
            $row = Schedule::withTrashed()->where('user_id', $uid)->where('uuid', $c['uuid'])->first();

            if ($row && (int) $row->client_updated_at >= $ts) {
                continue; // the server copy is newer (or identical): keep it
            }

            $start = $this->dt($c['start_at'] ?? null);
            $end   = $this->dt($c['end_at'] ?? null);
            if (!$start || !$end) {
                continue;
            }

            $row = $row ?: new Schedule();
            $row->forceFill([
                'user_id'           => $uid,
                'uuid'              => $c['uuid'],
                'title'             => $this->str($c['title'] ?? null, 150) ?? 'Untitled',
                'type'              => in_array($c['type'] ?? null, self::TYPES, true) ? $c['type'] : 'other',
                'calendar'          => ($c['calendar'] ?? null) === 'team' ? 'team' : 'my',
                'location'          => $this->str($c['location'] ?? null, 150),
                'technician'        => $this->str($c['technician'] ?? null, 120),
                'status'            => in_array($c['status'] ?? null, self::STATUSES, true) ? $c['status'] : 'approved',
                'start_at'          => $start,
                'end_at'            => $end < $start ? $start : $end,
                'all_day'           => !empty($c['all_day']),
                'color'             => in_array($c['color'] ?? null, self::COLORS, true) ? $c['color'] : null,
                'notes'             => $this->str($c['notes'] ?? null, 2000),
                'client_updated_at' => $ts,
                'deleted_at'        => !empty($c['deleted']) ? ($row->deleted_at ?? now()) : null,
            ])->save();
        }

        return response()->json($this->payload())->header('Cache-Control', 'no-store');
    }

    // ============================================================
    // TECHNICIAN — online-only plain CRUD
    // ============================================================

    public function technicianIndex()
    {
        abort_unless(auth()->user()->role === 'technician', 403);
        return view('technician.schedule', ['uid' => auth()->id()]);
    }

    /** Always-online list pull — no offline cache, no batching. */
    public function technicianEvents()
    {
        abort_unless(auth()->user()->role === 'technician', 403);
        return response()->json($this->payload())->header('Cache-Control', 'no-store');
    }

    public function technicianStore(Request $request)
    {
        abort_unless(auth()->user()->role === 'technician', 403);

        $start = $this->dt($request->input('start_at'));
        $end   = $this->dt($request->input('end_at'));
        abort_if(!$start || !$end, 422, 'Invalid start/end date.');

        (new Schedule())->forceFill($this->fillable($request, auth()->id(), $start, $end))->save();

        return response()->json($this->payload())->header('Cache-Control', 'no-store');
    }

    public function technicianUpdate(Request $request, string $uuid)
    {
        abort_unless(auth()->user()->role === 'technician', 403);

        $row = Schedule::where('user_id', auth()->id())->where('uuid', $uuid)->firstOrFail();

        $start = $this->dt($request->input('start_at'));
        $end   = $this->dt($request->input('end_at'));
        abort_if(!$start || !$end, 422, 'Invalid start/end date.');

        $row->forceFill($this->fillable($request, auth()->id(), $start, $end, $uuid))->save();

        return response()->json($this->payload())->header('Cache-Control', 'no-store');
    }

    public function technicianDestroy(string $uuid)
    {
        abort_unless(auth()->user()->role === 'technician', 403);

        Schedule::where('user_id', auth()->id())->where('uuid', $uuid)->firstOrFail()->delete();

        return response()->json($this->payload())->header('Cache-Control', 'no-store');
    }

    // ============================================================
    // Shared helpers
    // ============================================================

    private function fillable(Request $request, int $uid, string $start, string $end, ?string $uuid = null): array
    {
        return [
            'user_id'           => $uid,
            'uuid'              => $uuid ?: (string) Str::uuid(),
            'title'             => $this->str($request->input('title'), 150) ?? 'Untitled',
            'type'              => in_array($request->input('type'), self::TYPES, true) ? $request->input('type') : 'other',
            'calendar'          => $request->input('calendar') === 'team' ? 'team' : 'my',
            'location'          => $this->str($request->input('location'), 150),
            'technician'        => $this->str($request->input('technician'), 120),
            'status'            => in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : 'approved',
            'start_at'          => $start,
            'end_at'            => $end < $start ? $start : $end,
            'all_day'           => (bool) $request->input('all_day'),
            'color'             => in_array($request->input('color'), self::COLORS, true) ? $request->input('color') : null,
            'notes'             => $this->str($request->input('notes'), 2000),
            'client_updated_at' => (int) (microtime(true) * 1000),
        ];
    }

    private function payload(): array
    {
        $events = Schedule::where('user_id', auth()->id())
            ->orderBy('start_at')
            ->get()
            ->map(fn ($s) => [
                'uuid'       => $s->uuid,
                'title'      => $s->title,
                'type'       => $s->type,
                'calendar'   => $s->calendar,
                'location'   => $s->location,
                'technician' => $s->technician,
                'status'     => $s->status,
                'start_at'   => substr((string) $s->start_at, 0, 19),
                'end_at'     => substr((string) $s->end_at, 0, 19),
                'all_day'    => $s->all_day ? 1 : 0,
                'color'      => $s->color,
                'notes'      => $s->notes,
                'updated_at' => (int) $s->client_updated_at,
            ])
            ->values();

        return ['csrf' => csrf_token(), 'events' => $events];
    }

    private function str($v, int $max): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $v = trim((string) $v);
        return $v === '' ? null : Str::limit($v, $max, '');
    }

    private function dt($v): ?string
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $v)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}