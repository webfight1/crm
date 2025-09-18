import pandas as pd
import requests
from bs4 import BeautifulSoup
from urllib.parse import urlparse
import time
import sys
import os

def is_marketing_site(soup):
    # Check common marketing-related keywords in text
    text = soup.get_text().lower()
    marketing_keywords = [
        'marketing', 'advertising', 'ad agency', 'digital agency',
        'reklaam', 'turundus', 'meediaagentuur', 'reklaamiagentuur'
    ]
    return any(keyword in text for keyword in marketing_keywords)

def analyze_website(url):
    try:
        # Add scheme if not present
        if not url.startswith(('http://', 'https://')):
            url = 'http://' + url

        # Make request with timeout
        response = requests.get(url, timeout=10)
        
        # Get final URL after any redirects
        final_url = response.url
        
        # Parse domain
        domain = urlparse(final_url).netloc
        
        # Parse HTML
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Get title
        title = soup.title.string if soup.title else ''
        
        # Check if site is marketing related
        is_marketing = is_marketing_site(soup)
        
        return {
            'is_active': True,
            'final_url': final_url,
            'domain': domain,
            'title': title,
            'status_code': response.status_code,
            'is_marketing': is_marketing
        }
        
    except Exception as e:
        return {
            'is_active': False,
            'final_url': '',
            'domain': '',
            'title': '',
            'status_code': 0,
            'is_marketing': False
        }

def analyze_websites(csv_path, url_column):
    print(f"\nReading CSV file: {csv_path}")
    
    # Read CSV
    df = pd.read_csv(csv_path)
    
    if url_column not in df.columns:
        print(f"Error: Column '{url_column}' not found in CSV file.")
        print(f"Available columns: {', '.join(df.columns)}")
        return
    
    total = len(df)
    print(f"Found {total} websites to analyze")
    
    # Add new columns
    df['is_active'] = False
    df['final_url'] = ''
    df['domain'] = ''
    df['title'] = ''
    df['status_code'] = 0
    df['is_marketing'] = False
    
    # Analyze each website
    for i, row in df.iterrows():
        url = str(row[url_column]).strip()
        
        # Skip empty URLs
        if not url or url.lower() == 'nan':
            continue
            
        print(f"\nAnalyzing {i+1}/{total}: {url}")
        
        # Analyze website
        result = analyze_website(url)
        
        # Update dataframe
        for key, value in result.items():
            df.at[i, key] = value
            
        # Print status
        status = "Active" if result['is_active'] else "Inactive"
        print(f"Status: {status}")
        
        if result['is_active']:
            print(f"Title: {result['title']}")
            print(f"Domain: {result['domain']}")
            if result['is_marketing']:
                print("Type: Marketing/Advertising site")
                
        # Small delay between requests
        time.sleep(1)
    
    # Save results
    output_path = os.path.splitext(csv_path)[0] + '_analyzed.csv'
    df.to_csv(output_path, index=False)
    print(f"\nAnalysis complete! Results saved to: {output_path}")
    
    # Print summary
    total_sites = len(df)
    active_sites = df['is_active'].sum()
    inactive_sites = total_sites - active_sites
    marketing_sites = df['is_marketing'].sum()
    
    print(f"\nSummary:")
    print(f"Total websites analyzed: {total_sites}")
    print(f"Active websites: {active_sites}")
    print(f"Inactive websites: {inactive_sites}")
    print(f"Marketing/advertising websites found: {marketing_sites}")
    if active_sites > 0:
        print(f"Percentage of active sites that are marketing: {(marketing_sites/active_sites)*100:.1f}% (of active sites)")

if __name__ == "__main__":
    print("Website Analyzer")
    print("-" * 50)
    
    # Ask for CSV file path
    csv_path = input("Enter the CSV file path (e.g., websites.csv): ").strip()
    
    # Check if file exists
    if not os.path.exists(csv_path):
        print(f"Error: File '{csv_path}' not found!")
        sys.exit(1)
        
    # Ask for URL column name
    url_column = input("Enter the name of the URL column in the CSV: ").strip()
    
    # Run analysis
    analyze_websites(csv_path, url_column)
