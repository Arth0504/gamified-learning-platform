# Lesson Data Debugging & Diagnostic Report

This report outlines the SQL query traces, parameters, database records, and root cause analysis of the invisible lesson content bug inside the course syllabus.

---

## 1. SQL Queries Executed

During execution on [course-view.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/course-view.php), the following SQL statements are prepared and executed:

### A. Subjects/Chapters Fetch
```sql
SELECT * FROM subjects 
WHERE course_id = ? 
ORDER BY sort_order ASC, id ASC;
```
- **Parameter Bound**: `course_id = 1` (Basic Mathematics)
- **Result Output**: 
  - Subject ID 1: "Unit 1: Introduction to Numbers" (sort_order: 1)
  - Subject ID 2: "Unit 2: Simple Addition" (sort_order: 2)

### B. Lessons & Completion Progress Fetch
```sql
SELECT l.*, p.status AS completion_status 
FROM lessons l 
LEFT JOIN progress p ON l.id = p.lesson_id AND p.student_id = ? 
WHERE l.subject_id IN (?, ?) 
ORDER BY l.sort_order ASC, l.id ASC;
```
- **Parameters Bound**: 
  - `p.student_id = 1` (Ramesh Patel)
  - `l.subject_id IN (1, 2)` (Subject IDs)
- **Merged Params array**: `[1, 1, 2]`

---

## 2. Database Query Results

The database execution returned exactly **3 lesson records** associated with Course ID 1:

| Lesson ID | Subject ID | Lesson Title | Content Type | Duration |
| :--- | :--- | :--- | :--- | :--- |
| `1` | `1` | Counting 1 to 100 | text | 10 mins |
| `2` | `1` | Place Value: Tens and Ones | video | 12 mins |
| `3` | `2` | Addition Guide Book | pdf | 15 mins |

- These records are successfully populated into the mapping array `$lessons_by_subject`:
  - `$lessons_by_subject[1]` contains **2 lessons**.
  - `$lessons_by_subject[2]` contains **1 lesson**.

---

## 3. Root Cause Analysis

Data debugging confirmed that the **database queries and loop arrays are 100% correct**. The data reached the loop successfully. The bug was a **Tailwind CSS and Bootstrap styling conflict**:

1. **Tailwind CSS Utility Class**: Tailwind defines the class `.collapse` to hide HTML elements:
   ```css
   .collapse {
     visibility: collapse;
   }
   ```
2. **Bootstrap 5 Accordion Class**: Bootstrap 5 uses `.collapse` as the base class for accordion bodies, and adds `.show` (`display: block`) to make them visible.
3. **Conflict Defect**: When the student expanded the chapter accordion, Bootstrap added the class `.show` setting `display: block`. However, Tailwind's `.collapse` selector continued to apply `visibility: collapse;`. Because `visibility: collapse;` hides contents visually on the screen, the accordion body appeared as a **large empty white area**, even though the HTML elements were present in the DOM tree.

---

## 4. Fix Applied

An inline CSS override was added to [course-view.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/student/course-view.php) to restore accordion visibility:

```html
<style>
/* Resolve Tailwind CSS and Bootstrap collapse conflict */
.accordion-collapse.collapse {
    visibility: visible !important;
}
</style>
```

- This override disables Tailwind's hidden setting for accordions, allowing Bootstrap's default display states (`display: none` / `display: block`) to govern visibility. 
- Real-time browser verification screenshots (saved to [visible_debug_panel_1783483781370.png](file:///C:/Users/Arth%20Prajapati/.gemini/antigravity-ide/brain/7b130f1a-35a9-4d5c-8d95-5d86a72ea8b9/visible_debug_panel_1783483781370.png)) confirm that the lesson cards are now fully visible to the user.
