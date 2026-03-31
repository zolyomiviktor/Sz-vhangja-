<?php
/**
 * Segédfüggvény az avatarok egységes megjelenítéséhez.
 * Ha nincs kép, egy színes monogramot generál.
 */
function render_avatar($user_data, $size_class = 'medium', $options = [])
{
    $nickname = $user_data['nickname'] ?? 'U';
    $image = $user_data['profile_image'] ?? '';
    $extra_class = $options['class'] ?? '';

    // Ha van kép
    if (!empty($image)) {
        // Tisztítjuk az elérési utat: le szedjük az esetleges "uploads/" vagy "/" elejét
        $clean_path = preg_replace('/^(uploads\/|\/)/', '', $image);
        $full_path = 'uploads/' . $clean_path;

        if (file_exists($full_path)) {
            $alt = htmlspecialchars($nickname) . " profilképe";
            return '<img src="' . htmlspecialchars($full_path) . '" alt="' . $alt . '" class="avatar-' . $size_class . ' ' . $extra_class . '" style="object-fit: cover; display: block;">';
        }
    }

    // Ha nincs kép, monogramos helyőrző
    $initial = mb_strtoupper(mb_substr($nickname, 0, 1, "UTF-8"), "UTF-8");

    // Gradiens kiválasztása a nickname alapján (konzisztens marad)
    $grad_index = (crc32($nickname) % 6) + 1;

    return sprintf(
        '<div class="avatar-placeholder %s %s avatar-grad-%d" aria-hidden="true">%s</div>',
        $size_class,
        $extra_class,
        $grad_index,
        $initial
    );
}
?>