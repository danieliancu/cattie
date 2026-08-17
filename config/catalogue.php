<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy collection slugs
    |--------------------------------------------------------------------------
    |
    | Before the hierarchy refactor every category lived at /collections/{slug}.
    | Those URLs must keep resolving with a permanent redirect rather than
    | disappearing, so each retired slug maps to the slug of the category that
    | replaced it. The redirect controller then asks that category for its own
    | canonical URL, which keeps the mapping correct at either level of the
    | hierarchy and survives any future re-parenting.
    |
    | Slugs that still exist unchanged need no entry here — they are resolved by
    | a direct lookup. Entries can be removed once the old URLs stop receiving
    | traffic.
    |
    */

    'legacy_collection_slugs' => [
        'school-lunch' => 'school-everyday',
        'kids-drinkware' => 'personalised-water-bottles-for-kids',
        'school-accessories' => 'personalised-pencil-tins',
    ],

];
