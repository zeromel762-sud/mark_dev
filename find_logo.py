import urllib.request
import re

url = 'https://en.wikipedia.org/wiki/University_of_Eastern_Pangasinan'
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
try:
    html = urllib.request.urlopen(req).read().decode()
    logos = re.findall(r'(upload\.wikimedia\.org/wikipedia/[^"\s]+\.(?:png|jpg|svg))', html)
    if logos:
        for l in logos[:5]:
            print(l)
    else:
        print('No logos found')
        # Try to find any image
        imgs = re.findall(r'src="(//upload\.wikimedia\.org/wikipedia/[^"]+)"', html)
        for i in imgs[:5]:
            print(i)
except Exception as e:
    print(f'Error: {e}')