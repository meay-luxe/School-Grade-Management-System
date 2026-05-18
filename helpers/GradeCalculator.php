<?php
/* ============================================================
   GradeMS — Grade Calculator Helper
   File: helpers/GradeCalculator.php
   ============================================================ */

class GradeCalculator {

    /**
     * Compute the final grade from midterm and finals.
     * Formula: (Midterm × 0.50) + (Finals × 0.50)
     *
     * @return float|null  null if either value is missing
     */
    public static function computeFinal(?float $midterm, ?float $finals): ?float {
        if ($midterm === null || $finals === null) return null;
        return round(($midterm * 0.50) + ($finals * 0.50), 2);
    }

    /**
     * Assign a remark based on the computed final grade.
     *
     * @return string  'Passed' | 'Failed' | 'Incomplete'
     */
    public static function assignRemarks(?float $finalGrade): string {
        if ($finalGrade === null) return 'Incomplete';
        return $finalGrade >= 75 ? 'Passed' : 'Failed';
    }

    /**
     * Compute GPA from an array of grades.
     * Philippine university GPA scale (lower = better):
     *   95–100 → 1.00,  90–94 → 1.25,  85–89 → 1.50,
     *   80–84  → 1.75,  75–79 → 2.00,  70–74 → 2.50,
     *   65–69  → 3.00,  below 65 → 5.00 (Failed)
     *
     * @param array $grades  Array of ['final_grade' => float, 'units' => int]
     * @return float|null  Weighted GPA, or null if no graded subjects
     */
    public static function computeGPA(array $grades): ?float {
        $totalPoints = 0.0;
        $totalUnits  = 0;

        foreach ($grades as $g) {
            if ($g['final_grade'] === null) continue;   // skip incomplete
            $gpa         = self::gradeToGPA((float) $g['final_grade']);
            $units       = (int) $g['units'];
            $totalPoints += $gpa * $units;
            $totalUnits  += $units;
        }

        if ($totalUnits === 0) return null;
        return round($totalPoints / $totalUnits, 2);
    }

    /**
     * Convert a numerical grade (0–100) to GPA equivalent.
     */
    public static function gradeToGPA(float $grade): float {
        if ($grade >= 95) return 1.00;
        if ($grade >= 90) return 1.25;
        if ($grade >= 85) return 1.50;
        if ($grade >= 80) return 1.75;
        if ($grade >= 75) return 2.00;
        if ($grade >= 70) return 2.50;
        if ($grade >= 65) return 3.00;
        return 5.00;  // Failed
    }

    /**
     * Return the academic standing label based on GPA.
     *
     * @return string  "Dean's List" | "Good Standing" | "At Risk"
     */
    public static function getStanding(?float $gpa): string {
        if ($gpa === null)   return 'No Grades Yet';
        if ($gpa <= 1.75)    return "Dean's List";
        if ($gpa <= 2.50)    return 'Good Standing';
        return 'At Risk';
    }

    /**
     * Return a CSS class name for a standing label (used in HTML output).
     */
    public static function getStandingClass(?float $gpa): string {
        if ($gpa === null)  return '';
        if ($gpa <= 1.75)   return 'standing-deans';
        if ($gpa <= 2.50)   return 'standing-good';
        return 'standing-atrisk';
    }

    /**
     * Validate that a submitted grade is a number between 0 and 100.
     */
    public static function isValidGrade($value): bool {
        if ($value === null || $value === '') return true;  // null = not yet entered
        return is_numeric($value) && $value >= 0 && $value <= 100;
    }
}
