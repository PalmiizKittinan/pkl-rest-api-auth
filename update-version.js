const fs = require('fs');
const path = require('path');

const phpFileName = 'pkl-rest-api-auth.php';
const newVersion = process.env.TAG_VERSION;

if (!newVersion) {
    console.error("❌ Environment variable TAG_VERSION is not set");
    process.exit(1);
}

const fullPathToPhpFile = path.join(__dirname, phpFileName);

try {
    let content = fs.readFileSync(fullPathToPhpFile, 'utf-8');
    content = content.replace(/(\* Version: ).*/, `$1${newVersion}`);
    fs.writeFileSync(fullPathToPhpFile, content);
    console.log(`✅ Updated version to ${newVersion} in ${phpFileName}`);
} catch (error) {
    console.error(`❌ Error updating version:`, error.message);
    process.exit(1);
}