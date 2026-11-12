// Convert the complete PSA publication mirror into the app's stable address schema.
// Usage: node scripts/build-psgc.mjs path/to/barangay_flat.json
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
const source = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const byCode = new Map(source.map(row => [row.psgc_id, row]));
const result = { regions: [], provinces: [], cities: [], barangays: [] };
const cityTypes = ['municipality', 'component_city', 'highly_urbanized_city', 'independent_component_city'];
// The source labels the Isabela statistical parent group as a city; its actual
// child city is the selectable address unit, just as SGA is a group, not a province.
const cityParentGroups = new Set(source.filter(row => cityTypes.includes(row.type) && cityTypes.includes(byCode.get(row.parent_psgc_id)?.type)).map(row => row.parent_psgc_id));
function ancestor(row, type) {
    let parent = byCode.get(row.parent_psgc_id);
    while (parent) {
        if (parent.type === type) return parent.psgc_id;
        parent = byCode.get(parent.parent_psgc_id);
    }
    return '';
}
for (const row of source) {
    const item = { code: row.psgc_id, name: row.name };
    if (row.type === 'region') result.regions.push(item);
    else if (row.type === 'province') result.provinces.push({ ...item, region_code: ancestor(row, 'region') });
    else if (cityTypes.includes(row.type) && !cityParentGroups.has(row.psgc_id)) {
        result.cities.push({ ...item, region_code: ancestor(row, 'region'), province_code: ['highly_urbanized_city', 'independent_component_city'].includes(row.type) ? '' : ancestor(row, 'province') });
    } else if (row.type === 'barangay') {
        let parent = byCode.get(row.parent_psgc_id);
        // Manila's sub-municipalities share the City of Manila in the address form.
        if (parent.type === 'submunicipality') parent = byCode.get(parent.parent_psgc_id);
        result.barangays.push({ ...item, city_code: parent.psgc_id });
    }
}
if (result.regions.length !== 18 || result.provinces.length !== 82 || result.cities.length !== 1642 || result.barangays.length !== 42010) {
    throw new Error('Unexpected publication counts. Review the source before replacing the snapshot.');
}
// PSA's Q3 2026 release changes one geographic name; counts and parent codes are unchanged.
// https://psa.gov.ph/classification/psgc (release 12 October 2026)
const corrected = result.barangays.find(row => row.code === '1001322005');
if (!corrected || !['Lambangan', 'Lambagan'].includes(corrected.name)) throw new Error('Review the Q3 2026 name correction before refreshing this source.');
corrected.name = 'Lambagan';
mkdirSync('resources/data', { recursive: true });
writeFileSync('resources/data/psgc.json', JSON.stringify(result));
console.log(Object.fromEntries(Object.entries(result).map(([key, rows]) => [key, rows.length])));
