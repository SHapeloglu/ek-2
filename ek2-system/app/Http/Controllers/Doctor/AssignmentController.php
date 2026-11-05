<?php
namespace App\Http\Controllers\Doctor;
use App\Http\Controllers\Controller;
use App\Models\Assignment;

class AssignmentController extends Controller {
    public function index() {
        $liste = Assignment::with('workplace')->where('doctor_id', auth()->id())
            ->where('starts_at','>=', now()->subDays(7)->startOfDay())
            ->orderBy('starts_at')->limit(200)->get();
        return view('doctor.assignments.index', compact('liste'));
    }
}
