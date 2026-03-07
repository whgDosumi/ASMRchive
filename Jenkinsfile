pipeline {
    agent any
    options {
        // Only keep 10 builds
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }
    environment {
        // Sanitize BUILD_TAG for use in container/image/network/volume names.
        // BUILD_TAG is jenkins-{JOB_NAME}-{BUILD_NUMBER} e.g. jenkins-PR-Builder-42
        BUILD_TAG_CLEAN = "${env.BUILD_TAG.replaceAll('[^a-zA-Z0-9_-]', '-').toLowerCase()}"
    }
    parameters {
        // Determines whether we should skip the manual review step
        booleanParam(defaultValue: true, description: "Skip manual review?", name: "SKIP_REVIEW")
        booleanParam(defaultValue: false, description: "Use Image Cache?", name: "USE_CACHE")
        booleanParam(defaultValue: false, description: "Suppress Telegram Notifications", name: "SUPPRESS_NOTIFS")
        booleanParam(defaultValue: false, description: "Pause Between Steps", name: "Pause")
    }
    stages {
        stage ("Initialization") {
            steps {
                script {
                    echo "Initializing"
                    // Assign a unique host port based on which executor is running this build.
                    // Executors are numbered 0-4, giving ports 4445-4449.
                    // The Jenkins server is configured with a proxy server, so you can access at
                    // jenkins-${env.EXECUTOR_NUMBER + 1}.wronghood.net
                    env.BUILD_PORT = "${4445 + ((env.EXECUTOR_NUMBER ?: '0') as Integer)}"
                    def skip_manual = params.SKIP_REVIEW
                    def use_cache = params.USE_CACHE
                    if (env.JOB_NAME.contains("PR Builder")) {
                        skip_manual = false
                        use_cache = false
                        echo "PR Build - Forcing manual review and fresh build."
                    } else if (env.JOB_NAME.contains("Branch Builder")) {
                        use_cache = true
                        echo "Branch Build - Using cache for speed."
                    }
                    env.skip_manual_dynamic = skip_manual.toString()
                    env.use_cache_dynamic = use_cache.toString()

                    // Derive unique resource names for this build from BUILD_TAG_CLEAN.
                    // All names are prefixed with 'asmrchive' and use a two-letter
                    // abbreviation to identify the resource type (net, vol).
                    env.APP_IMAGE      = "asmrchive-${env.BUILD_TAG_CLEAN}"
                    env.TEST_IMAGE     = "asmrchive-test-${env.BUILD_TAG_CLEAN}"
                    env.CONTAINER_NAME = "asmrchive-${env.BUILD_TAG_CLEAN}"
                    env.NETWORK_NAME   = "asmrchive-net-${env.BUILD_TAG_CLEAN}"
                    env.VOLUME_NAME    = "asmrchive-vol-${env.BUILD_TAG_CLEAN}"
                    echo "Build Tag Clean: ${env.BUILD_TAG_CLEAN}"
                    echo "App image:       ${env.APP_IMAGE}"
                    echo "Test image:      ${env.TEST_IMAGE}"
                    echo "Container:       ${env.CONTAINER_NAME}"
                    echo "Network:         ${env.NETWORK_NAME}"
                    echo "Volume:          ${env.VOLUME_NAME}"
                    echo "Live Demo:       https://jenkins-${(env.EXECUTOR_NUMBER as Integer) + 1}.wronghood.net"
                }
            }
        }
        stage ("Build Image") {
            steps {
                echo "Building image (cache: ${env.use_cache_dynamic})"
                script {
                    def cacheFlag = env.use_cache_dynamic == "true" ? "" : "--no-cache --pull"
                    sh "podman --storage-opt ignore_chown_errors=true build ${cacheFlag} --label project=asmrchive --label image_type=app -t ${APP_IMAGE} ."
                }
            }
        }
        stage ("Spawn Container") {
            steps {
                echo "Creating podman network: ${NETWORK_NAME}"
                sh "podman network create ${NETWORK_NAME}"
                echo "Creating podman volume: ${VOLUME_NAME}"
                sh "podman volume create --label project=asmrchive ${VOLUME_NAME}"
                echo "Constructing Container on port ${BUILD_PORT}"
                sh """
                podman create \
                    -p ${BUILD_PORT}:80 \
                    --name ${CONTAINER_NAME} \
                    --network ${NETWORK_NAME} \
                    --network-alias asmrchive-app \
                    --volume ${VOLUME_NAME}:/var/ASMRchive \
                    --label project=asmrchive \
                    -e HOST_URL=http://localhost/ \
                    ${APP_IMAGE}
                """
                echo "Starting Container"
                sh "podman container start ${CONTAINER_NAME}"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Spawn Container - COMPLETE

                        Container is running at: https://jenkins-${(env.EXECUTOR_NUMBER as Integer) + 1}.wronghood.net

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                        Click 'Proceed' when ready to continue to Unit Tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Update YT-DLP") {
            steps {
                // This is necessary since layer caching sometimes prevents the latest version being present.
                echo "Ensure yt-dlp is up to date in the container."
                sh "podman exec ${CONTAINER_NAME} cd /var/python && uv sync --upgrade-package yt-dlp"
            }
        }
        stage ("Unit Tests") {
            steps {
                sh "podman exec ${CONTAINER_NAME} cd /var/python && uv run test.py"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Unit Tests - COMPLETE

                        Container is running at: https://jenkins-${(env.EXECUTOR_NUMBER as Integer) + 1}.wronghood.net

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                        Click 'Proceed' when ready to continue to Integration Tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Downgrade YT-DLP") {
            steps {
                // Downgrade yt-dlp to an older version.
                // This is so the integration tests can check the update button on the webpage, and make sure it works.
                sh "cd /var/python && uv add yt-dlp==2026.02.04"
            }
        }
        stage ("Integration Tests") {
            steps {
                script {
                    // Build test container (chrome webdriver and selenium)
                    def cacheFlag = env.use_cache_dynamic == "true" ? "" : "--no-cache --pull"
                    sh "podman --storage-opt ignore_chown_errors=true build ${cacheFlag} --label project=asmrchive --label image_type=test -t ${TEST_IMAGE} testing/"
                }
                withCredentials([
                    usernamePassword(credentialsId: 'asmrchive-owner-creds', passwordVariable: 'OWNER_PASSWORD', usernameVariable: 'OWNER_USERNAME'),
                    usernamePassword(credentialsId: 'asmrchive-admin-creds', passwordVariable: 'ADMIN_PASSWORD', usernameVariable: 'ADMIN_USERNAME')
                ]) {
                    sh """
                    podman run --rm \
                        --network ${NETWORK_NAME} \
                        -e OWNER_USERNAME="\${OWNER_USERNAME}" \
                        -e OWNER_PASSWORD="\${OWNER_PASSWORD}" \
                        -e ADMIN_USERNAME="\${ADMIN_USERNAME}" \
                        -e ADMIN_PASSWORD="\${ADMIN_PASSWORD}" \
                        ${TEST_IMAGE} \
                        --test dlp \
                        --url http://asmrchive-app:80
                    """
                }
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Integration Tests - COMPLETE

                        Container is running at: https://jenkins-${(env.EXECUTOR_NUMBER as Integer) + 1}.wronghood.net

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                        Click 'Proceed' when ready to continue to DLP Update tests.
                        """
                        input(message: pauseMsg)
                    }
                }
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
                    def message = "Build requires manual review\n[Jenkins Job](${jobUrl})\n[Live Demo](https://jenkins-${(env.EXECUTOR_NUMBER as Integer) + 1}.wronghood.net)"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN'), string(credentialsId: 'dosumi-chat-id', variable: 'CHAT_ID')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${CHAT_ID} -d text='${message}' -d parse_mode=Markdown"
                    }
                    // Prompt user to review the build.
                    def pauseMsg = """
                    Stage: All Tests - COMPLETE

                    Container is running at: https://jenkins-${(env.EXECUTOR_NUMBER as Integer) + 1}.wronghood.net

                    Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                    Click 'Proceed' when ready to finalize the build.
                    """
                    input(id: 'userInput', message: pauseMsg)
                }
            }
        }
    }
    post {
        always {
            // Remove this build's container and network.
            // The volume is kept for retention (cleaned up below).
            sh "podman container rm -f ${CONTAINER_NAME} || true"
            sh "podman network rm -f ${NETWORK_NAME} || true"

            // Keep the 5 most recently built app images; remove older ones.
            // podman images lists newest-first, so tail -n +6 is everything past the first 5.
            // We deduplicate IDs before counting — cached builds share the same image layer,
            // so the same ID can appear multiple times (once per tag) and inflate the count.
            sh """
                all_ids=\$(podman images \
                    --filter 'label=project=asmrchive' \
                    --filter 'label=image_type=app' \
                    --format '{{.ID}}')

                unique_ids=\$(echo "\$all_ids" | awk '!seen[\$0]++')
                excess=\$(echo "\$unique_ids" | tail -n +6)

                if [ -n "\$excess" ]; then
                    echo "\$excess" | xargs podman rmi -f || true
                fi
            """

            // Same for test images.
            sh """
                all_ids=\$(podman images \
                    --filter 'label=project=asmrchive' \
                    --filter 'label=image_type=test' \
                    --format '{{.ID}}')

                unique_ids=\$(echo "\$all_ids" | awk '!seen[\$0]++')
                excess=\$(echo "\$unique_ids" | tail -n +6)

                if [ -n "\$excess" ]; then
                    echo "\$excess" | xargs podman rmi -f || true
                fi
            """

            // Keep the 5 most recently created volumes; remove older ones.
            // podman volume ls has no guaranteed sort order, so we inspect each
            // volume's creation time and sort explicitly before trimming.
            sh """
                all_vols=\$(
                    for vol in \$(podman volume ls --filter 'label=project=asmrchive' --format '{{.Name}}'); do
                        created=\$(podman volume inspect "\$vol" --format '{{.CreatedAt}}')
                        echo "\$created \$vol"
                    done | sort -r | awk '{print \$NF}'
                )

                excess=\$(echo "\$all_vols" | tail -n +6)

                if [ -n "\$excess" ]; then
                    echo "\$excess" | xargs podman volume rm || true
                fi
            """
        }
        success {
            script {
                if (!params.SUPPRESS_NOTIFS) {
                    if (!env.JOB_NAME.contains("Daily Master Build")) {
                        def message = "Build Successful: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                        withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN'), string(credentialsId: 'dosumi-chat-id', variable: 'CHAT_ID')]) {
                            sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${CHAT_ID} -d text='${message}'"
                        }
                    }
                }
            }
        }
        failure {
            script {
                if (!params.SUPPRESS_NOTIFS) {
                    def message = "Build Failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN'), string(credentialsId: 'dosumi-chat-id', variable: 'CHAT_ID')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${CHAT_ID} -d text='${message}'"
                    }
                }
            }
        }
    }
}