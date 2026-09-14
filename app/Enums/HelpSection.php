<?php

namespace App\Enums;

/**
 * A Help section — one grouping of Help articles in the help centre (ADR-0025).
 * Sections mirror the app's navigation: Getting started, then one section per app
 * area as the backfill batches land. The section is the manifest's grouping axis
 * and the middle crumb of an article's breadcrumb.
 *
 * Backed string enum: the stored value is the section's slug, and its human label
 * is chrome — a key in the `help` lang file, translated like the rest of the app.
 */
enum HelpSection: string
{
    case GettingStarted = 'getting-started';
    case Dashboard = 'dashboard';
    case MyHours = 'my-hours';
    case Directory = 'directory';
    case News = 'news';
    case Groups = 'groups';
    case Scheduling = 'scheduling';
    case HoursAndReports = 'hours-and-reports';
    case Emailing = 'emailing';
    case Settings = 'settings';
    case Support = 'support';

    /** The lang key for this section's translated label (chrome, ADR-0004). */
    public function labelKey(): string
    {
        return "help.section.{$this->value}";
    }
}
