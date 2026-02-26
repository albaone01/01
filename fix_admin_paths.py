import os
import re

BASE = os.path.join('public', 'admin')


def main():
    for root, _, files in os.walk(BASE):
        for fname in files:
            if not fname.endswith('.php'):
                continue
            path = os.path.join(root, fname)
            depth = os.path.relpath(root, BASE).count(os.sep)

            # inc path like ../../inc or ../../../inc
            inc_rel = '../' * (depth + 2) + 'inc'
            header_rel = f"{inc_rel}/header.php"

            with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()

            new = content

            # Fix require_once to inc/*
            new = re.sub(
                r"require_once ['\"](?:\.\./)+inc/((?:[A-Za-z0-9_]+)\.php)['\"]",
                lambda m: f"require_once '{inc_rel}/{m.group(1)}'",
                new,
            )

            # Fix include header
            new = re.sub(
                r"include ['\"](?:\.\./)+inc/header\.php['\"]",
                f"include '{header_rel}'",
                new,
            )

            # Fix assets references to /assets/
            new = re.sub(
                r"(href|src)=['\"](?:\.\./)*assets/",
                lambda m: f"{m.group(1)}='/assets/",
                new,
            )

            # Fix redirects to login if any ../login.php
            new = re.sub(
                r"header\(['\"](?:\.\./)+login\.php['\"]\)",
                lambda m: f"header('Location: {'../' * (depth + 1)}login.php')",
                new,
            )

            # Special handling for settings.php uploads path
            if fname == 'settings.php':
                new = new.replace(
                    "__DIR__ . '/assets/uploads'",
                    "dirname(__DIR__) . '/assets/uploads'",
                )
                new = new.replace("'assets/uploads/", "'/assets/uploads/")

            if new != content:
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(new)
                print(f"updated {path}")


if __name__ == '__main__':
    main()