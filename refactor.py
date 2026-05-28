import re
import os

files = [
    'views/modules/inventory_app.php',
    'views/modules/checkin.php',
    'views/modules/oil_form.php',
    'views/modules/oil_report.php',
    'views/modules/dispatch_map.php',
    'views/modules/user_settings.php'
]

emojis_to_lucide = {
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
}

for file_path in files:
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Modals - backdrop
    content = content.replace('z-[100]', 'z-[80]')
    content = content.replace('z-50', 'z-[80]')
    
    # Modals - dialog (add z-[90] to the inner container)
    content = re.sub(r'(class="[^"]*bg-white[^"]*rounded-(?:2xl|xl|\[[0-9a-z]+\])[^"]*shadow-(?:2xl|xl)[^"]*)(")', r'\1 z-[90]\2', content)

    # CARDS
    content = re.sub(r'bg-white\s+p-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+shadow-[a-z]+\s+border\s+border-gray-[0-9]+', 'card', content)
    content = re.sub(r'bg-white\s+rounded-[a-z0-9\[\]-]+\s+shadow-[a-z]+\s+border\s+border-gray-[0-9]+\s+p-[0-9]+', 'card', content)
    content = re.sub(r'bg-white\s+rounded-[a-z0-9\[\]-]+\s+shadow-[a-z]+\s+border\s+border-gray-[0-9]+', 'card', content)
    
    content = re.sub(r'bg-white\s+rounded-\[2rem\]\s+shadow-xl\s+p-[0-9]+\s+border\s+border-gray-[0-9]+', 'card', content)
    content = re.sub(r'bg-white\s+rounded-\[2rem\]\s+shadow-xl\s+overflow-hidden', 'card overflow-hidden', content)

    # INPUTS
    content = re.sub(r'border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+text-[a-z]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+', 'input', content)
    content = re.sub(r'px-[0-9]+\s+py-[0-9\.]+\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+', 'input', content)
    content = re.sub(r'w-full\s+px-[0-9]+\s+py-[0-9\.]+\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+(?:\s+shadow-sm)?', 'input', content)
    content = re.sub(r'w-full\s+px-[0-9]+\s+py-[0-9\.]+\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+(?:\s+shadow-sm)?', 'input', content)
    
    content = re.sub(r'border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+px-[0-9]+\s+py-[0-9\.]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-(?:bold|medium)\s+text-(?:gray|slate)-[0-9]+', 'input', content)
    content = re.sub(r'px-3\s+py-2\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+text-[a-z]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+', 'input', content)
    content = re.sub(r'px-4\s+py-3\s+border\s+border-(?:gray|slate)-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+font-bold\s+text-(?:gray|slate)-[0-9]+', 'input', content)
    content = re.sub(r'w-full\s+border\s+border-gray-300\s+rounded-xl\s+px-4\s+py-3\s+focus:ring-2\s+focus:ring-indigo-500\s+font-bold\s+text-gray-800', 'input', content)
    content = re.sub(r'w-full\s+pl-[0-9]+\s+pr-[0-9]+\s+py-[0-9\.]+\s+border\s+border-gray-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+focus:border-[a-z]+-[0-9]+\s+transition-colors\s+font-(?:bold|medium)(?:\s+appearance-none\s+bg-white|\s+text-slate-[0-9]+)?', 'input', content)
    content = re.sub(r'w-full\s+pl-[0-9]+\s+pr-[0-9]+\s+py-[0-9\.]+\s+border\s+border-gray-[0-9]+\s+rounded-[a-z0-9\[\]-]+\s+focus:ring-2\s+focus:ring-[a-z]+-[0-9]+\s+focus:border-[a-z]+-[0-9]+\s+transition-colors', 'input', content)

    # BUTTONS
    content = re.sub(r'bg-indigo-600\s+hover:bg-indigo-700\s+text-white\s+rounded-[a-z0-9\[\]-]+\s+font-black\s+shadow-lg\s+shadow-indigo-[0-9]+\s+transform\s+transition-all\s+active:scale-[0-9]+(?:\s+disabled:opacity-[0-9]+\s+disabled:cursor-not-allowed)?', 'btn-primary', content)
    content = re.sub(r'bg-indigo-600\s+hover:bg-indigo-700\s+text-white\s+px-[0-9]+\s+py-[0-9\.]+\s+rounded-[a-z0-9\[\]-]+\s+text-[a-z]+\s+font-(?:medium|bold|black)(?:\s+shadow-sm)?(?:\s+transition)?', 'btn-primary', content)
    content = re.sub(r'bg-indigo-600\s+text-white\s+rounded-[a-z0-9\[\]-]+\s+font-bold\s+shadow-md\s+hover:bg-indigo-700', 'btn-primary', content)
    content = re.sub(r'px-4\s+py-2\s+bg-indigo-600\s+text-white\s+rounded-[a-z0-9\[\]-]+\s+font-bold\s+shadow-md\s+hover:bg-indigo-700', 'btn-primary', content)
    content = re.sub(r'px-6\s+py-2.5\s+rounded-[a-z0-9\[\]-]+\s+bg-indigo-600\s+text-white\s+hover:bg-indigo-700\s+font-black\s+shadow-lg\s+shadow-indigo-[0-9]+\s+transition-colors', 'btn-primary', content)

    # Additional user_settings input replacements:
    content = re.sub(r'w-full\s+px-5\s+py-3\.5\s+rounded-2xl\s+bg-slate-50\s+border-transparent\s+focus:bg-white\s+focus:ring-2\s+focus:ring-indigo-500/20\s+focus:border-indigo-500\s+font-bold\s+transition-all', 'input', content)
    content = re.sub(r'w-full\s+px-5\s+py-3\.5\s+rounded-2xl\s+bg-orange-50\s+border-transparent\s+focus:bg-white\s+focus:ring-2\s+focus:ring-orange-500/20\s+focus:border-orange-500\s+font-bold\s+transition-all', 'input', content)
    content = re.sub(r'w-full\s+px-5\s+py-3\.5\s+rounded-2xl\s+bg-amber-50\s+border-transparent\s+focus:bg-white\s+focus:ring-2\s+focus:ring-amber-500/20\s+focus:border-amber-500\s+font-bold\s+transition-all', 'input', content)

    # user_settings btn-primary
    content = re.sub(r'bg-indigo-600\s+hover:bg-indigo-700\s+text-white\s+px-[0-9]+\s+py-[0-9\.]+\s+rounded-[a-z0-9\[\]-]+\s+font-black\s+text-sm\s+transition-all\s+shadow-lg\s+shadow-indigo-[0-9]+', 'btn-primary', content)
    
    # EMOJIS TO LUCIDE
    for emoji, icon in emojis_to_lucide.items():
        content = content.replace(emoji, icon)

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
