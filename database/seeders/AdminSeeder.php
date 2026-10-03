<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\Blog;
use App\Models\Bus;
use App\Models\BusStudent;
use App\Models\Classroom;
use App\Models\ClassroomStudent;
use App\Models\ClassroomSubject;
use App\Models\ClassroomTeacher;
use App\Models\DailyReport;
use App\Models\Degree;
use App\Models\DegreeStudent;
use App\Models\Level;
use App\Models\PaymentFee;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentPayment;
use App\Models\StudentReview;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\Walla;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $result = Admin::create([
            'name' => 'Admin',
            'job_title' => 'Administrator',
            'email' => 'admin@admin.com',
            'phone' => '0100100100',
            'password' => bcrypt('password'),
            'is_active' => 'active',
            'role_id' => 1,
        ]);

        $role = Role::find(1);
        $result->syncRoles([]);
        $result->assignRole($role->name);

    }
}
