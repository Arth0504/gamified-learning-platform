# EduQuest Rural Learning Platform: Component Map

This catalog lists the key visual components designed in the Stitch screens. These components will be built in the codebase as reusable frontend parts.

---

## 1. Navbar Component

The navigation header is fixed to the top viewport (`h-16`) and spans the full screen width.

*   **Structure**:
    ```
    +---------------------------------------------------------------------------------+
    | [Brand Logo & Title]   [Search Input]   [Metrics: Streak & Points] [Avatar Dropdown] |
    +---------------------------------------------------------------------------------+
    ```
*   **Elements**:
    *   **Brand Logo**: "EduBridge" written in bold with secondary color accents.
    *   **Search Box**: Responsive search input (`hidden md:flex`) with a magnifying glass search icon.
    *   **Gamified Badges**: 
        *   *Daily Streak*: Fire icon with numerical value (`14 Days`).
        *   *XP/Points*: Coin icon with value (`12,450 XP`).
    *   **Language Selector**: Select dropdown displaying language options (English, Hindi, Gujarati).
    *   **Theme Toggle**: Moon/Sun toggle for dark and light modes.
    *   **User Avatar Dropdown**: Circular avatar thumbnail, triggering a dropdown menu containing role display, profile settings, and logout actions.

---

## 2. Sidebar Component

The left sidebar runs full height (`h-screen`, `w-64`) and handles primary page navigation.

*   **Structure**:
    *   **Organization header**: High-fidelity logo and subtitle.
    *   **Menu Group**: Vertical list of page links with icon triggers.
    *   **Promotion / Upgrade Card**: Small dashboard widget explaining user rewards.
    *   **Footer Group**: Help options, profile shortcuts, and logout controls.
*   **Interaction/States**:
    *   **Default**: Transparent item background with muted text.
    *   **Hover**: Translates text slightly to the right (`hover:translate-x-1`) and adds background shading.
    *   **Active**: Adds left border highlight (`border-l-4 border-secondary`), colors background container, and bolds text.
    *   **Mobile Mode**: Auto-collapses off-canvas, toggled by the menu button in the top navbar.

---

## 3. Cards Component

The cards represent the most versatile container unit, providing visual separation for info groups.

*   **Variants**:
    *   **Standard Cards**: Solid white background, rounded edges (`rounded-xl`), and very soft outer drop shadow (`shadow-md`).
    *   **Glassmorphic Cards (`.glass-card`)**: Semitransparent white background (`rgba(255, 255, 255, 0.4)`) with backdrop filter blur (`blur(16px)`). Soft transition styling.
    *   **Brand Promotion Card**: Solid background container (`bg-primary` or linear gradient) with white text.
*   **Key Card UI Mockups**:
    *   **Stats Widget Card**: Left-aligned icon box, right-aligned metric values (e.g., Total XP, Active Students).
    *   **Course Progress Card**: Image thumbnail, course name, description, linear progress bar, and "Continue" button.
    *   **Badge Display Card**: Center-aligned circular badge graphic with hovering tooltip text.

---

## 4. Forms Component

Forms provide standard elements for inputs and account updates.

*   **Elements**:
    *   **Floating-Label Fields**: Inputs with subtle outline borders that highlight when focused (`focus:border-primary`).
    *   **Password input groups**: Toggle visibility buttons using eye/eye-off icons.
    *   **Upload Drag-and-Drop Card**: Dashed border container containing an icon, upload instructions, and a file input trigger.
    *   **Form CTA Buttons**:
        *   *Primary CTA*: Solid brand blue, shadows on hover.
        *   *Special CTA*: Gradient transition buttons (`bg-gradient-to-r from-primary to-secondary`).
        *   *Secondary CTA*: Transparent/white background, thin outline border.

---

## 5. Tables Component

Tables display lists, progress lists, and records.

*   **Elements**:
    *   **Search and Filter headers**: Quick dropdown filter options and search input.
    *   **Row structure**: Flex row grid columns containing student avatars, names, levels, badges, points, progress status (badge chip), and action buttons.
    *   **Pagination footer**: Page indicators and previous/next buttons.
*   **Responsive Styling**: Columns containing minor metadata are set to hide (`hidden md:table-cell` or similar flex equivalent) on mobile screens.

---

## 6. Quiz UI Component

The quiz interface is a specialized dashboard optimized for single-task focus.

*   **Layout Structure**:
    *   **Progress Indicators**: Text label "Question X of Y" and a progress bar showing completion percentage.
    *   **Timer Display**: Circular or badge indicator displaying remaining time (`id="global-timer"`).
    *   **Question Card**: Centered elevated box containing the text and associated visual diagrams/images.
    *   **Option selectors**: Wide button selectors. States:
        *   *Default*: Clear gray border with transparent hover background.
        *   *Selected*: Thick primary color border with background color tint.
        *   *Success (Correct response)*: Green background, green border, checkmark icon indicator.
        *   *Failure (Incorrect response)*: Red background, red border, cross icon indicator.

---

## 7. Leaderboard UI Component

The leaderboard gamifies progress, encouraging competition.

*   **Layout Structure**:
    *   **Podium View (Top 3)**:
        *   *First Place ( Priya Sharma )*: Centered, elevated card, gold crown icon, largest avatar, grandmaster tag.
        *   *Second Place ( Arjun Singh )*: Left side, standard avatar size, math wizard tag.
        *   *Third Place ( Omar Khan )*: Right side, standard avatar size, code ninja tag.
    *   **Details Listing**: Standard list items containing ranks, user avatars, full name, XP points, and unlocked badge tags.

---

## 8. Achievement UI Component

The achievement interface showcases user progression.

*   **Elements**:
    *   **Stats Summary Bar**: Display showing total badges unlocked, student rank tier, and current learning streak count.
    *   **Badge Gallery Grid**: 
        *   *Unlocked Badge*: Colorful glowing icon container, title text, and click actions.
        *   *Locked Badge*: Semi-transparent overlay, grayed-out badge icon, and lock icon overlay.
    *   **Badge Detail Dialog / Modal**: Popup overlay describing the achievement requirements, date unlocked, and sharing buttons.
