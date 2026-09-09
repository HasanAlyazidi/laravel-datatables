<?php

namespace HasanAlyazidi\DataTables;

/**
 * Badge markup for the current theme.
 *
 * Tables name a meaning (Badge::SUCCESS), never a CSS class, so the same
 * table renders correctly on every supported framework. The frameworks
 * differ more than they look:
 *
 *   - Bootstrap 5 colours badge text white, so warning/info/light add text-dark
 *   - Bootstrap 4 works out the text colour itself (color-yiq)
 *   - Bootstrap 3 has no colourable .badge at all — coloured badges are
 *     .label — and no light/dark, which fall back to its neutral grey
 *
 * An unknown style is passed through as literal CSS classes, which covers
 * custom chips such as 'urgency-badge urgency-badge-late'.
 */
final class Badge
{
    const PRIMARY = 'primary';

    const SECONDARY = 'secondary';

    const SUCCESS = 'success';

    const DANGER = 'danger';

    const WARNING = 'warning';

    const INFO = 'info';

    const LIGHT = 'light';

    const DARK = 'dark';

    /**
     * theme => [style => css classes]
     *
     * @var array
     */
    private static $classes = [
        Theme::BOOTSTRAP5 => [
            self::PRIMARY => 'badge bg-primary',
            self::SECONDARY => 'badge bg-secondary',
            self::SUCCESS => 'badge bg-success',
            self::DANGER => 'badge bg-danger',
            self::WARNING => 'badge bg-warning text-dark',
            self::INFO => 'badge bg-info text-dark',
            self::LIGHT => 'badge bg-light text-dark',
            self::DARK => 'badge bg-dark',
        ],
        Theme::BOOTSTRAP4 => [
            self::PRIMARY => 'badge badge-primary',
            self::SECONDARY => 'badge badge-secondary',
            self::SUCCESS => 'badge badge-success',
            self::DANGER => 'badge badge-danger',
            self::WARNING => 'badge badge-warning',
            self::INFO => 'badge badge-info',
            self::LIGHT => 'badge badge-light',
            self::DARK => 'badge badge-dark',
        ],
        Theme::BOOTSTRAP3 => [
            self::PRIMARY => 'label label-primary',
            self::SECONDARY => 'label label-default',
            self::SUCCESS => 'label label-success',
            self::DANGER => 'label label-danger',
            self::WARNING => 'label label-warning',
            self::INFO => 'label label-info',
            self::LIGHT => 'label label-default',
            self::DARK => 'label label-default',
        ],
    ];

    /**
     * A badge in one of the named styles, e.g. Badge::html(Badge::SUCCESS, 'Active').
     * $extraClasses is for positioning helpers such as float utilities.
     */
    public static function html(string $style, string $label, string $extraClasses = ''): string
    {
        $class = trim(self::classes($style).' '.$extraClasses);

        return '<span class="'.e($class).'">'.e($label).'</span>';
    }

    /**
     * A badge coloured from stored values, e.g. Badge::custom($plan->bg_color,
     * $plan->text_color, $plan->name). Colours are escaped, so a stored value
     * cannot break out of the style attribute.
     */
    public static function custom(string $background, string $text, string $label, string $extraClasses = ''): string
    {
        $class = trim(self::classes(self::SECONDARY).' '.$extraClasses);
        $style = 'background-color:'.$background.';color:'.$text;

        return '<span class="'.e($class).'" style="'.e($style).'">'.e($label).'</span>';
    }

    /**
     * The CSS classes for a style under the current theme. An unrecognised
     * style is returned as-is, so custom classes pass straight through.
     */
    private static function classes(string $style): string
    {
        $theme = Theme::current();
        $map = isset(self::$classes[$theme]) ? self::$classes[$theme] : self::$classes[Theme::BOOTSTRAP5];

        return isset($map[$style]) ? $map[$style] : $style;
    }
}
