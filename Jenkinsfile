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
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }
    parameters {
        // Determines whether we should skip the manual review step
        booleanParam(defaultValue: true, description: "Skip manual review?", name: "SKIP_REVIEW")
        booleanParam(defaultValue: false, description: "Use Image Cache?", name: "USE_CACHE")
        booleanParam(defaultValue: false, description: "Suppress Telegram Notifications", name: "SUPPRESS_NOTIFS")
        booleanParam(defaultValue: false, description: "Pause Between Steps", name: "Pause")
        text(name: "Build ID", defaultValue: "", description: "Build ID")
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
                script {
                    echo "Removing existing testing containers"
                    sh "podman ps -a -q -f ancestor=jenkins-asmrchive | xargs -I {} podman container rm -f {} || true" // Removes all containers that exist under the image
                    if (!params.USE_CACHE) {
                        echo "Removing existing image"
                        sh "podman image rm jenkins-asmrchive || true"
                    }
                }
            }   
        }
        stage ("Build Image") {
            steps {
                echo "Building image..."
                sh "podman --storage-opt ignore_chown_errors=true build -t jenkins-asmrchive ."
            }
        }
        stage ("Spawn Container") {
            steps {
                echo "Constructing Container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    -e HOST_URL=http://localhost/ \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
            }
        }
        stage ("Unit Tests") {
            steps {
                sh "podman exec -it jenkins-asmrchive python /var/python_app/test.py"
            }
        }
        stage ("Integration Tests") {
            steps {
                sh "podman --storage-opt ignore_chown_errors=true build -t asmrchive-test testing/"
                sh "podman run --network=\"host\" asmrchive-test"
            }
        }
        stage ("Integration Tests (rproxy)") { 
            steps {
                echo "Removing first container"
                sh "podman container stop jenkins-asmrchive"
                sh "podman container rm jenkins-asmrchive"
                echo "Constructing container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    -e HOST_URL=http://localhost/Jenkins_ASMRchive/ \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
                sh "podman run --network=\"host\" asmrchive-test --url http://localhost/Jenkins_ASMRchive/"
            }
        }
        stage ("Integration Test (DLP Updates)") {
            steps{
                echo "Removing first container"
                sh "podman container stop jenkins-asmrchive"
                sh "podman container rm jenkins-asmrchive"
                echo "Constructing container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    -e DLP_VER=2024.12.06 \
                    -e HOST_URL=http://localhost/Jenkins_ASMRchive/ \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
                sh "podman run \
                        --network=\"host\" \
                        asmrchive-test \
                        --test dlponly"
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
                if (!params.SUPPRESS_NOTIFS) {
                    if (!env.JOB_NAME.contains("Daily Master Build")) {
                        def message = "Build Successful: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                        def chatId = "222789278"
                        withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                            sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}'"
                        }
                    }
                }
            }
        }
        failure {
            script {
                if (!params.SUPPRESS_NOTIFS) {
                    def message = "Build Failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                    def chatId = "222789278"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}'"
                    }
                }
            }
        }
    }
}