const fs = require('fs');

const files = [
    'views/modules/inventory_app.php',
    'views/modules/checkin.php',
    'views/modules/oil_form.php',
    'views/modules/oil_report.php',
    'views/modules/dispatch_map.php',
    'views/modules/user_settings.php'
];

const emojis_to_lucide = {
    '📦': '<i data-lucide="package" class="w-5 h-5 inline-block"></i>',
    '📊': '<i data-lucide="bar-chart-2" class="w-5 h-5 inline-block"></i>',
    '📥': '<i data-lucide="download" class="w-5 h-5 inline-block"></i>',
    '📤': '<i data-lucide="upload" class="w-5 h-5 inline-block"></i>',
    '🔄': '<i data-lucide="refresh-cw" class="w-5 h-5 inline-block"></i>',
    '🕒': '<i data-lucide="clock" class="w-5 h-5 inline-block"></i>',
    '🗑️': '<i data-lucide="trash-2" class="w-5 h-5 inline-block"></i>',
    '🔍': '<i data-lucide="search" class="w-5 h-5 inline-block"></i>',
    '🏷️': '<i data-lucide="tag" class="w-5 h-5 inline-block"></i>',
    '📋': '<i data-lucide="clipboard" class="w-5 h-5 inline-block"></i>',
    '📁': '<i data-lucide="folder" class="w-5 h-5 inline-block"></i>',
    '⬇️': '<i data-lucide="arrow-down" class="w-5 h-5 inline-block"></i>',
    '✓': '<i data-lucide="check" class="w-5 h-5 inline-block"></i>',
    '👤': '<i data-lucide="user" class="w-5 h-5 inline-block"></i>',
    '📑': '<i data-lucide="file-text" class="w-5 h-5 inline-block"></i>',
    '✅': '<i data-lucide="check-circle" class="w-5 h-5 inline-block"></i>',
    '📸': '<i data-lucide="camera" class="w-5 h-5 inline-block"></i>',
    '⚙️': '<i data-lucide="settings" class="w-5 h-5 inline-block"></i>',
    '✏️': '<i data-lucide="edit-2" class="w-5 h-5 inline-block"></i>',
    '⛽': '<i data-lucide="fuel" class="w-5 h-5 inline-block"></i>',
    '⏰': '<i data-lucide="clock" class="w-5 h-5 inline-block"></i>',
    '🚗': '<i data-lucide="car" class="w-5 h-5 inline-block"></i>',
    '💧': '<i data-lucide="droplet" class="w-5 h-5 inline-block"></i>',
    '💰': '<i data-lucide="dollar-sign" class="w-5 h-5 inline-block"></i>',
    '🚀': '<i data-lucide="rocket" class="w-5 h-5 inline-block"></i>',
    '📍': '<i data-lucide="map-pin" class="w-5 h-5 inline-block"></i>',
    '🤖': '<i data-lucide="bot" class="w-5 h-5 inline-block"></i>',
    '👥': '<i data-lucide="users" class="w-5 h-5 inline-block"></i>',
    '⏳': '<i data-lucide="hourglass" class="w-5 h-5 inline-block"></i>'
};

files.forEach(filePath => {
    let content = fs.readFileSync(filePath, 'utf-8');

    // Modals - backdrop
    content = content.replace(/z-\[100\]/g, 'z-[80]');
    content = content.replace(/z-50/g, 'z-[80]');
    
    // Modals - dialog (add z-[90] to the inner container)
    content = content.replace(/(class="[^"]*bg-white[^"]*rounded-(?:2xl|xl|\[[0-9a-z]+\])[^"]*shadow-(?:2xl|xl)[^"]*)(")/g, '$1 z-[90]$2');

    // CARDS
    content = content.replace(/bg-white\s+p-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+shadow-[a-z]+\s+border\s+border-gray-[0-9]+/g, 'card');
    content = content.replace(/bg-white\s+rounded-[a-z0-9\[\]-]+\s+shadow-[a-z]+\s+border\s+border-gray-[0-9]+\s+p-[0-9]+/g, 'card');
    content = content.replace(/bg-white\s+rounded-[a-z0-9\[\]-]+\s+shadow-[a-z]+\s+border\s+border-gray-[0-9]+/g, 'card');
    
    content = content.replace(/bg-white\s+rounded-\[2rem\]\s+shadow-xl\s+p-[0-9]+\s+border\s+border-gray-[0-9]+/g, 'card');
    content = content.replace(/bg-white\s+rounded-\[2rem\]\s+shadow-xl\s+overflow-hidden/g, 'card overflow-hidden');

    // INPUTS
    content = content.replace(/border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+text-[a-z]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+/g, 'input');
    content = content.replace(/px-[0-9]+\s+py-[0-9\.]+\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+/g, 'input');
    content = content.replace(/w-full\s+px-[0-9]+\s+py-[0-9\.]+\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+(?:\s+shadow-sm)?/g, 'input');
    content = content.replace(/w-full\s+px-[0-9]+\s+py-[0-9\.]+\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+(?:\s+shadow-sm)?/g, 'input');
    
    content = content.replace(/border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+px-[0-9]+\s+py-[0-9\.]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+/g, 'input');
    content = content.replace(/px-3\s+py-2\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+text-[a-z]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+/g, 'input');
    content = content.replace(/px-4\s+py-3\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-bold\s+text-(?:gray|slate)-[0-9]+/g, 'input');
    content = content.replace(/w-full\s+border\s+border-gray-300\s+rounded-xl\s+px-4\s+py-3\s+focus:ring-2\s+focus:ring-indigo-500\s+font-bold\s+text-gray-800/g, 'input');
    content = content.replace(/w-full\s+pl-[0-9]+\s+pr-[0-9]+\s+py-[0-9\.]+\s+border\s+border-gray-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+focus:border-[a-z]+-[0-9]+\s+transition-colors\s+font-(?:bold|medium)(?:\s+appearance-none\s+bg-white|\s+text-slate-[0-9]+)?/g, 'input');
    content = content.replace(/w-full\s+pl-[0-9]+\s+pr-[0-9]+\s+py-[0-9\.]+\s+border\s+border-gray-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+focus:border-[a-z]+-[0-9]+\s+transition-colors/g, 'input');
    
    // Additional generic class groupings
    content = content.replace(/border\s+border-gray-300\s+rounded-lg\s+focus:ring-2\s+focus:ring-blue-500\s+transition-colors/g, 'input');

    // BUTTONS
    content = content.replace(/bg-indigo-600\s+hover:bg-indigo-700\s+text-white\s+rounded-[a-z0-9\[\]-]+\s+font-black\s+shadow-lg\s+shadow-indigo-[0-9]+\s+transform\s+transition-all\s+active:scale-[0-9]+(?:\s+disabled:opacity-[0-9]+\s+disabled:cursor-not-allowed)?/g, 'btn-primary');
    content = content.replace(/bg-indigo-600\s+hover:bg-indigo-700\s+text-white\s+px-[0-9]+\s+py-[0-9\.]+\s+rounded-[a-z0-9\[\]-]+\s+text-[a-z]+\s+font-(?:medium|bold|black)(?:\s+shadow-sm)?(?:\s+transition)?/g, 'btn-primary');
    content = content.replace(/bg-indigo-600\s+text-white\s+rounded-[a-z0-9\[\]-]+\s+font-bold\s+shadow-md\s+hover:bg-indigo-700/g, 'btn-primary');
    content = content.replace(/px-4\s+py-2\s+bg-indigo-600\s+text-white\s+rounded-[a-z0-9\[\]-]+\s+font-bold\s+shadow-md\s+hover:bg-indigo-700/g, 'btn-primary');
    content = content.replace(/px-6\s+py-2\.5\s+rounded-[a-z0-9\[\]-]+\s+bg-indigo-600\s+text-white\s+hover:bg-indigo-700\s+font-black\s+shadow-lg\s+shadow-indigo-[0-9]+\s+transition-colors/g, 'btn-primary');

    // Additional user_settings input replacements:
    content = content.replace(/w-full\s+px-5\s+py-3\.5\s+rounded-2xl\s+bg-slate-50\s+border-transparent\s+focus:bg-white\s+focus:ring-2\s+focus:ring-indigo-500\/20\s+focus:border-indigo-500\s+font-bold\s+transition-all/g, 'input');
    content = content.replace(/w-full\s+px-5\s+py-3\.5\s+rounded-2xl\s+bg-orange-50\s+border-transparent\s+focus:bg-white\s+focus:ring-2\s+focus:ring-orange-500\/20\s+focus:border-orange-500\s+font-bold\s+transition-all/g, 'input');
    content = content.replace(/w-full\s+px-5\s+py-3\.5\s+rounded-2xl\s+bg-amber-50\s+border-transparent\s+focus:bg-white\s+focus:ring-2\s+focus:ring-amber-500\/20\s+focus:border-amber-500\s+font-bold\s+transition-all/g, 'input');

    // user_settings btn-primary
    content = content.replace(/bg-indigo-600\s+hover:bg-indigo-700\s+text-white\s+px-[0-9]+\s+py-[0-9\.]+\s+rounded-[a-z0-9\[\]-]+\s+font-black\s+text-sm\s+transition-all\s+shadow-lg\s+shadow-indigo-[0-9]+/g, 'btn-primary');
    
    // dispatch_map inputs
    content = content.replace(/border-slate-200\s+rounded-lg\s+px-[0-9]+\s+py-[0-9\.]+\s+h-[0-9]+\s+focus:ring-0\s+text-slate-600/g, 'input');
    
    // other specific matches if any missed
    content = content.replace(/border\s+border-gray-300\s+rounded-lg\s+focus:ring-2\s+focus:ring-emerald-500/g, 'input');
    
    // EMOJIS TO LUCIDE
    Object.keys(emojis_to_lucide).forEach(emoji => {
        content = content.split(emoji).join(emojis_to_lucide[emoji]);
    });

    fs.writeFileSync(filePath, content, 'utf-8');
});
