import subprocess
import shutil
import os

"""
phpDocumentor build script for PHP API reference generation.

This generates comprehensive API reference documentation from PHP source code
and copies it to the main documentation site under /reference/api/php/.

This complements the feature-centric docs which explain concepts with examples.
"""

def run_phpdoc():
    try:
        # phpdoc is run from Docker, so this whole command is a bit complex
        # see the readme: it has an alias to create phpdoc, then shows
        # how to call it with its arguments

        docker_command = [
            'docker', 'run', '--rm', '-v', f"{os.getcwd()}:/data", 'phpdoc/phpdoc:3',
            '--directory=src',
            '--target=docs',
            '--template=phpdocumentor-markdown-customised/themes/markdown',
            '--title=Respectify PHP Library',
            '-c', 'phpdoc.xml'
        ]

        # Run the Docker command
        subprocess.run(docker_command, check=True)
        print("✅ phpDocumentor ran successfully.")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Error running phpDocumentor: {e}")
        return False

def copy_docs():
    """Copy generated docs to the Docusaurus API reference folder."""
    src_folder = 'docs'
    dest_folder = '../discussion-arena-docgen/respectify-docs/docs/reference/api/php'

    dest_folder_full_path = os.path.abspath(dest_folder)
    print(f"📁 Copying to: {dest_folder_full_path}")

    try:
        # Ensure the destination directory exists
        os.makedirs(dest_folder, exist_ok=True)

        # Remove old docs
        if os.path.exists(dest_folder):
            shutil.rmtree(dest_folder)

        # Copy the contents of the docs folder to the destination folder
        shutil.copytree(src_folder, dest_folder)
        print("✅ PHP API reference docs copied successfully.")
        return True
    except Exception as e:
        print(f"❌ Error copying docs: {e}")
        return False

def run_schema_generator():
    """Run the Python schema generator to update PHP schemas and docs."""
    print("📝 Generating schemas from Python source of truth...")

    schema_gen_path = '../respectify-python/schema_generator.py'

    try:
        subprocess.run(['python', schema_gen_path], check=True)
        print("✅ Schemas generated successfully.")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Error running schema generator: {e}")
        return False

if __name__ == "__main__":
    print("🔨 Building PHP API Reference Documentation")
    print("=" * 60)

    if run_schema_generator():
        if run_phpdoc():
            if copy_docs():
                print("=" * 60)
                print("✅ Build completed successfully!")
                print()
                print("API reference available at: /docs/reference/api/php/")
            else:
                exit(1)
        else:
            exit(1)
    else:
        exit(1)