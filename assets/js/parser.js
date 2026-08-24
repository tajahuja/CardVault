/**
 * CardVault Deterministic Text Parsing & Heuristics Engine
 * Parses raw OCR text into structured contact fields.
 */

window.parseOCRText = function(rawText) {
    const data = {
        fullName: '',
        firstName: '',
        lastName: '',
        jobTitle: '',
        company: '',
        phone: '',
        alternatePhone: '',
        email: '',
        alternateEmail: '',
        website: '',
        linkedinUrl: '',
        address: '',
        city: '',
        state: '',
        country: '',
        postalCode: ''
    };

    if (!rawText) return data;

    // Split text into lines, trim, and filter out completely empty entries
    let lines = rawText.split('\n')
        .map(line => line.trim())
        .filter(line => line.length > 1);

    // Keep an unmodified copy of clean lines for contextual references
    const originalLines = [...lines];

    // 1. EXTRACT EMAILS
    const emailRegex = /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/g;
    const allEmails = [];
    lines.forEach(line => {
        const matches = line.match(emailRegex);
        if (matches) {
            matches.forEach(m => allEmails.push(m));
        }
    });

    if (allEmails.length > 0) {
        data.email = allEmails[0];
        if (allEmails.length > 1) {
            data.alternateEmail = allEmails[1];
        }
        // Remove email strings from lines to clean up further processing
        lines = lines.map(line => line.replace(emailRegex, '').trim()).filter(l => l.length > 0);
    }

    // 2. EXTRACT WEBSITES & LINKEDIN
    const urlRegex = /\b(?:https?:\/\/)?(?:www\.)?([a-zA-Z0-9-]+\.[a-zA-Z]{2,})[^\s]*\b/g;
    const linkedinPattern = /linkedin\.com|lnkd\.in/i;
    
    const urls = [];
    const linkedinUrls = [];
    
    lines.forEach(line => {
        const matches = line.match(urlRegex);
        if (matches) {
            matches.forEach(url => {
                if (linkedinPattern.test(url)) {
                    linkedinUrls.push(url);
                } else {
                    urls.push(url);
                }
            });
        }
    });

    if (urls.length > 0) {
        data.website = urls[0];
    }
    if (linkedinUrls.length > 0) {
        data.linkedinUrl = linkedinUrls[0];
    }

    // Remove URLs from lines
    lines = lines.map(line => line.replace(urlRegex, '').trim()).filter(l => l.length > 0);

    // 3. EXTRACT PHONE NUMBERS
    // Matches Indian +91 formats, international formats with dashes/parentheses/spaces, etc.
    const phoneRegex = /(?:\+?\d{1,3}[-.\s]?)?\(?\d{3,5}\)?[-.\s]?\d{3,5}[-.\s]?\d{3,5}/g;
    const allPhones = [];
    
    lines.forEach(line => {
        const matches = line.match(phoneRegex);
        if (matches) {
            matches.forEach(p => {
                // Ensure it has at least 7 digits to filter out random zip codes or numbers
                const digits = p.replace(/\D/g, '');
                if (digits.length >= 7 && digits.length <= 15) {
                    allPhones.push(p.trim());
                }
            });
        }
    });

    if (allPhones.length > 0) {
        data.phone = allPhones[0];
        if (allPhones.length > 1) {
            data.alternatePhone = allPhones[1];
        }
        // Remove phone numbers from lines
        lines = lines.map(line => line.replace(phoneRegex, '').trim()).filter(l => l.length > 0);
    }

    // 4. EXTRACT POSTAL CODE (ZIP / PIN Code)
    const pinRegex = /\b\d{6}\b|\b\d{5}\b|\b\d{3}\s?\d{3}\b/;
    for (let i = 0; i < lines.length; i++) {
        const match = lines[i].match(pinRegex);
        if (match) {
            data.postalCode = match[0].replace(/\s/g, '');
            lines[i] = lines[i].replace(pinRegex, '').trim();
            break;
        }
    }

    // 5. EXTRACT JOB TITLE / DESIGNATION (Look for common keywords)
    const jobKeywords = [
        'Director', 'Manager', 'Founder', 'CEO', 'Co-Founder', 'President', 'VP', 'Vice President',
        'Architect', 'Engineer', 'Developer', 'Consultant', 'Specialist', 'Partner', 'Lead', 'Head',
        'Officer', 'Executive', 'Analyst', 'Designer', 'Representative', 'Accountant', 'Advisor',
        'Partner', 'Consultant', 'Proprietor', 'Managing Director'
    ];
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const isJob = jobKeywords.some(keyword => {
            const regex = new RegExp('\\b' + keyword + '\\b', 'i');
            return regex.test(line);
        });
        
        if (isJob && line.split(/\s+/).length <= 5) {
            data.jobTitle = line;
            lines.splice(i, 1);
            break; // Find first matching job title line
        }
    }

    // 6. EXTRACT COMPANY NAME / ORGANIZATION (Heuristic based on suffixes & context)
    const companyKeywords = [
        'Pvt Ltd', 'Pvt. Ltd.', 'Ltd', 'Ltd.', 'Limited', 'LLP', 'Inc', 'Inc.', 'Incorporated', 'Co', 'Co.',
        'Corp', 'Corp.', 'Corporation', 'Group', 'Solutions', 'Technologies', 'Services', 'Systems', 
        'Industries', 'Hotels', 'Associates', 'Partners', 'Ventures', 'Enterprises', 'Travels', 'Tours',
        'Industries', 'Enterprises', 'Software', 'Labs'
    ];

    // Check lines for company suffixes
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const isCompany = companyKeywords.some(keyword => {
            // Check for exact word or phrase match
            const escapedKeyword = keyword.replace('.', '\\.');
            const regex = new RegExp('\\b' + escapedKeyword + '\\b', 'i');
            return regex.test(line);
        });

        if (isCompany && line.split(/\s+/).length <= 6) {
            data.company = line;
            lines.splice(i, 1);
            break;
        }
    }

    // Fallback: If no company found, check if website matches a line name
    if (!data.company && data.website) {
        // Extract domain name (e.g., 'acme' from 'www.acme.com')
        const domainMatch = data.website.replace(/https?:\/\//i, '').replace(/www\./i, '').split('.')[0];
        if (domainMatch && domainMatch.length > 2) {
            const domainRegex = new RegExp(domainMatch, 'i');
            for (let i = 0; i < lines.length; i++) {
                if (domainRegex.test(lines[i]) && lines[i].split(/\s+/).length <= 4) {
                    data.company = lines[i];
                    lines.splice(i, 1);
                    break;
                }
            }
        }
    }

    // 7. EXTRACT CITY, STATE, COUNTRY (Common vocabulary lookup)
    const cities = ['Mumbai', 'Delhi', 'Bengaluru', 'Bangalore', 'Pune', 'Chennai', 'Kolkata', 'Hyderabad', 'Ahmedabad', 'Goa', 'Dubai', 'London', 'New York', 'Singapore'];
    const states = ['Maharashtra', 'Karnataka', 'Tamil Nadu', 'Delhi', 'Gujarat', 'Goa', 'California', 'Texas', 'New York'];
    const countries = ['India', 'United States', 'US', 'USA', 'UK', 'United Kingdom', 'UAE', 'Singapore'];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        
        cities.forEach(c => {
            if (new RegExp('\\b' + c + '\\b', 'i').test(line)) {
                data.city = c;
            }
        });
        
        states.forEach(s => {
            if (new RegExp('\\b' + s + '\\b', 'i').test(line)) {
                data.state = s;
            }
        });
        
        countries.forEach(co => {
            if (new RegExp('\\b' + co + '\\b', 'i').test(line)) {
                data.country = co;
            }
        });
    }

    // 8. EXTRACT PERSON'S NAME (Clean contextual name filter)
    const addressKeywords = [
        'Road', 'Rd', 'Street', 'St', 'Lane', 'Ln', 'Building', 'Bldg', 'Floor', 'Fl', 'Sector', 
        'Plot', 'Phase', 'City', 'State', 'Pin', 'Box', 'Avenue', 'Ave', 'Highway', 'Hwy', 'Zone',
        'Plot No', 'Opp', 'Near', 'Behind', 'Beside', 'Industrial Estate', 'Area', 'Dist', 'District'
    ];

    const cleanLinesForName = lines.filter(line => {
        // Exclude lines with digits
        if (/\d/.test(line)) return false;
        
        // Exclude lines containing characters typically found in labels or phones
        if (/[+:@#\/_]/.test(line)) return false;
        if (/tel|mob|phone|email|website|web|www|fax|email/i.test(line)) return false;

        // Exclude lines with common address keywords
        const isAddress = addressKeywords.some(keyword => {
            const regex = new RegExp('\\b' + keyword + '\\b', 'i');
            return regex.test(line);
        });
        if (isAddress) return false;
        
        // Exclude lines that match city/state/country already extracted
        if (data.city && new RegExp('\\b' + data.city + '\\b', 'i').test(line)) return false;
        if (data.state && new RegExp('\\b' + data.state + '\\b', 'i').test(line)) return false;
        if (data.country && new RegExp('\\b' + data.country + '\\b', 'i').test(line)) return false;

        // Exclude lines containing job title keywords (designation check)
        const containsJobKeyword = jobKeywords.some(keyword => {
            const regex = new RegExp('\\b' + keyword + '\\b', 'i');
            return regex.test(line);
        });
        if (containsJobKeyword) return false;

        // Exclude lines containing company name keywords (organization check)
        const containsCompanyKeyword = companyKeywords.some(keyword => {
            const escapedKeyword = keyword.replace('.', '\\.');
            const regex = new RegExp('\\b' + escapedKeyword + '\\b', 'i');
            return regex.test(line);
        });
        if (containsCompanyKeyword) return false;

        // Exclude lines that are too long or too short (Names are usually 2 to 4 words)
        const wordCount = line.split(/\s+/).length;
        if (wordCount < 2 || wordCount > 4) return false;

        return true;
    });

    if (cleanLinesForName.length > 0) {
        // The first remaining clean line is our best candidate for the person's name
        data.fullName = cleanLinesForName[0];
        
        // Split name into first and last name
        const nameParts = data.fullName.split(/\s+/);
        data.firstName = nameParts[0];
        data.lastName = nameParts.slice(1).join(' ');
        
        // Remove name from lines
        lines = lines.filter(l => l !== data.fullName);
    } else if (lines.length > 0) {
        // Fallback: Use the first non-filtered line that is short and has no digits
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const wordCount = line.split(/\s+/).length;
            if (wordCount >= 2 && wordCount <= 4 && !/\d/.test(line) && !/tel|mob|phone|email|website/i.test(line)) {
                data.fullName = line;
                const nameParts = line.split(/\s+/);
                data.firstName = nameParts[0];
                data.lastName = nameParts.slice(1).join(' ');
                lines.splice(i, 1);
                break;
            }
        }
    }

    // 9. COMPILE REMAINING LINES INTO STREET ADDRESS
    // Any lines left over that contain numbers or common address words are compiled
    const addressLines = lines.filter(line => {
        const containsDigits = /\d/.test(line);
        const containsAddressWords = addressKeywords.some(keyword => {
            const regex = new RegExp('\\b' + keyword + '\\b', 'i');
            return regex.test(line);
        });
        return containsDigits || containsAddressWords;
    });

    if (addressLines.length > 0) {
        data.address = addressLines.join(', ');
    }

    return data;
};
