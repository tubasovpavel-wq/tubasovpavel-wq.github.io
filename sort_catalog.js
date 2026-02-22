const fs = require('fs');

let html = fs.readFileSync('index.html', 'utf8');

// Find the machines array content
const startMarker = 'const machines = [';
const startIdx = html.indexOf(startMarker);
if (startIdx === -1) { console.log('ERROR: machines array not found'); process.exit(1); }

const arrayStart = startIdx + startMarker.length;
// Find matching closing bracket
let depth = 1;
let arrayEnd = arrayStart;
for (let i = arrayStart; i < html.length; i++) {
    if (html[i] === '[') depth++;
    if (html[i] === ']') { depth--; if (depth === 0) { arrayEnd = i; break; } }
}

const arrayContent = html.substring(arrayStart, arrayEnd);

// Split into lines
const lines = arrayContent.split(/\r?\n/);

// Group lines into sections (comment + items)
const sections = [];
let currentSection = { comment: '', items: [], blankLines: 0 };

for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed) {
        continue;
    }
    if (trimmed.startsWith('//')) {
        if (currentSection.comment || currentSection.items.length > 0) {
            sections.push(currentSection);
        }
        currentSection = { comment: line, items: [] };
    } else if (trimmed.startsWith('{')) {
        currentSection.items.push(line);
    }
}
if (currentSection.comment || currentSection.items.length > 0) {
    sections.push(currentSection);
}

// Sort items within each section by price
for (const section of sections) {
    section.items.sort((a, b) => {
        const priceA = a.match(/price:\s*'([\d\s]+)/);
        const priceB = b.match(/price:\s*'([\d\s]+)/);
        if (!priceA || !priceB) return 0;
        const numA = parseInt(priceA[1].replace(/\s/g, ''));
        const numB = parseInt(priceB[1].replace(/\s/g, ''));
        return numA - numB;
    });
}

// Rebuild array content
const sorted = sections.map(s => {
    return [s.comment, ...s.items].join('\r\n');
}).join('\r\n\r\n');

// Replace in HTML
const newHtml = html.substring(0, arrayStart) + '\r\n' + sorted + '\r\n        ' + html.substring(arrayEnd);
fs.writeFileSync('index.html', newHtml, 'utf8');

// Print summary
for (const s of sections) {
    const prices = s.items.map(item => {
        const m = item.match(/price:\s*'([\d\s]+)/);
        return m ? parseInt(m[1].replace(/\s/g, '')) : 0;
    });
    console.log(`${s.comment.trim()}: [${prices.join(', ')}]`);
}
console.log('\nDONE - sorted ' + sections.length + ' sections');
