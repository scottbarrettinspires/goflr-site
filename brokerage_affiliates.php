<?php
// Companies affiliated with the brokerage, shown on the Directory above Active agents. Edit here (or ask Claude to).
// 'contacts' lists up to 2 people (name, title, phone, email, photo).
// 'cat' is a favorites category key (title, lender, inspector, signco, photo, installer): the company shows first in that
// category for every agent. 'photo' is a file in private_assets/ (served to signed-in agents only by affphoto.php).
return [
    'brokerage' => 'Florida Luxury Realty',
    'companies' => [
        ['name' => 'Elite Closing Group', 'cat' => 'title', 'kind' => 'Title company', 'website' => 'https://www.eliteclosinggroup.com',
         'contacts' => [
            ['name' => 'Sophia Arcese', 'title' => 'Manager and Title Agent', 'phone' => '727-372-4637', 'email' => 'manager@eliteclosinggroup.com', 'photo' => 'elite-manager.jpg'],
            ['name' => 'Missy Wharton', 'title' => 'Title Processor', 'phone' => '727-372-4637', 'email' => 'closings@eliteclosinggroup.com', 'photo' => 'elite-processor.jpg'],
         ]],
    ],
];
