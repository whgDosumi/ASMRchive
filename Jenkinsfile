pipeline {
    agent any
    options {
        // For throttling other builds
        throttleJobProperty(
        categories: ['ASMRchive'],
        throttleEnabled: true,
        throttleOption: 'category'
        )
        // Only keep 3 builds
        buildDiscarder(logRotator(numToKeepStr: '3'))
    }
    parameters {
        // Determines whether we should skip the manual review step
        booleanParam(defaultValue: true, description: "Skip manual review?", name: "SKIP_REVIEW")
    }
    stages {
        stage ("Initialization") {
            steps {
                script {
                    echo "Initializing"
                    def skip_manual = params.SKIP_REVIEW
                    if (env.JOB_NAME.contains("PR Builder")) {
                        skip_manual = false
                        echo "forcing manual review"
                    }
                    env.skip_manual_dynamic = skip_manual
                }
            }
        }
        stage ("Tidy Up") { // Cleans up environment to ensure we don't have artifacts from old builds
            steps {
                // We'll call the image created by this pipeline jenkins-asmrchive
                // Containers will be called the same
                echo "Removing existing testing containers"
                sh "podman ps -a -q -f ancestor=jenkins-asmrchive | xargs -I {} podman container rm -f {} || true" // Removes all containers that exist under the image
                echo "Removing existing image"
                sh "podman image rm jenkins-asmrchive || true"
            }   
        }
        stage ("Build Image") {
            steps {
                echo "Building image..."
                sh "podman --storage-opt ignore_chown_errors=true build -t jenkins-asmrchive ."
            }
        }
        stage ("Create Container") {
            steps {
                echo "Constructing Container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
            }
        }
        stage ("Testing w/ Port") {
            steps {
                sh "podman --storage-opt ignore_chown_errors=true build -t asmrchive-test testing/"
                //sh "podman run --network=\"host\" asmrchive-test"
            }
        }
        stage ("Reconstructing Container") {
            steps {
                echo "Removing first container"
                sh "podman container stop jenkins-asmrchive"
                sh "podman container rm jenkins-asmrchive"
                echo "Constructing container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
            }
        }
        stage ("Testing Reverse Proxy") { 
            steps {
                sh "podman run --network=\"host\" asmrchive-test 'http://localhost/Jenkins_ASMRchive'"
            }
        }
        stage ("Manual Review") {
            when {
                expression {
                    return env.skip_manual_dynamic == "false"
                }
            }
            steps {
                script {
                    // Send a telegram message
                    def baseJenkinsUrl = env.JENKINS_URL
                    def jobNamePath = env.JOB_NAME.replaceAll("/", "/job/")
                    def jobUrl = "${baseJenkinsUrl}job/${jobNamePath}/"
                    def message = "Build requires manual review\n[Jenkins Job](${jobUrl})\n[Live Demo](http://onion.lan:4445)"
                    def chatId = "222789278"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}' -d parse_mode=Markdown"
                    }
                    // Prompt user to review the build.
                    input(id: 'userInput', message: 'Is the build okay?')
                }
                
            }
        }
    }
    post {
        success {
            script {
                def message = "Build Successful: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                def chatId = "222789278"
                withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                    sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}'"
                }
            }
        }
        failure {
            script {
                def message = "Build Failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                def chatId = "222789278"
                withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                    sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}'"
                }
            }
        }
    }
}